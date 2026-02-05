using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using Mip;
using Mip.Models;
using YamlDotNet.Serialization;
using YamlDotNet.Serialization.NamingConventions;

var builder = WebApplication.CreateBuilder(args);

// Configure JSON serialization
builder.Services.ConfigureHttpJsonOptions(options =>
{
    options.SerializerOptions.DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull;
    options.SerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower;
});

builder.Services.AddHttpClient();

var app = builder.Build();

// Initialize node
InitializeNode(args);

// Serve static files
app.UseStaticFiles();

// ============================================================================
// Helper functions
// ============================================================================

Store GetStore() => Store.Current;
NodeIdentity GetIdentity() => Store.Current.NodeIdentity!;
MipClient GetClient(IHttpClientFactory factory) => new(GetIdentity(), factory.CreateClient());

IResult MipResponse(bool succeeded, object? data = null)
{
    return Results.Json(new
    {
        meta = new { succeeded },
        data = data ?? new { }
    });
}

async Task<Dictionary<string, object?>> GetJsonBody(HttpRequest request)
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();
    if (string.IsNullOrEmpty(body))
        return new Dictionary<string, object?>();

    var options = new JsonSerializerOptions { PropertyNameCaseInsensitive = true };
    return JsonSerializer.Deserialize<Dictionary<string, object?>>(body, options) ?? new Dictionary<string, object?>();
}

(string? mipId, Connection? connection, string? publicKey, string? error) VerifyMipRequest(HttpRequest request, string body)
{
    var mipId = request.Headers["X-MIP-MIP-IDENTIFIER"].FirstOrDefault();
    var timestamp = request.Headers["X-MIP-TIMESTAMP"].FirstOrDefault();
    var signature = request.Headers["X-MIP-SIGNATURE"].FirstOrDefault();
    var publicKeyHeader = request.Headers["X-MIP-PUBLIC-KEY"].FirstOrDefault();

    if (string.IsNullOrEmpty(mipId) || string.IsNullOrEmpty(timestamp) || string.IsNullOrEmpty(signature))
        return (null, null, null, "Missing MIP headers");

    if (!Signature.TimestampValid(timestamp))
        return (null, null, null, "Invalid timestamp");

    var store = GetStore();
    var connection = store.FindConnection(mipId);
    string? publicKey = null;

    if (connection != null)
    {
        publicKey = connection.PublicKey;
    }
    else if (!string.IsNullOrEmpty(publicKeyHeader))
    {
        publicKey = Encoding.UTF8.GetString(Convert.FromBase64String(publicKeyHeader));
    }

    if (string.IsNullOrEmpty(publicKey))
        return (null, null, null, "Unknown sender");

    var bodyToVerify = string.IsNullOrEmpty(body) ? null : body;
    if (!Signature.VerifyRequest(publicKey, signature, timestamp, request.Path.Value!, bodyToVerify))
        return (null, null, null, "Invalid signature");

    return (mipId, connection, publicKey, null);
}

void SendEndorsementToConnection(Connection connection, IHttpClientFactory factory)
{
    Task.Run(async () =>
    {
        try
        {
            var endorsement = Endorsement.Create(GetIdentity(), connection.MipIdentifier, connection.PublicKey!);
            var client = GetClient(factory);
            await client.SendEndorsementAsync(connection.MipUrl, endorsement);
            GetStore().LogActivity($"Sent endorsement to {connection.OrganizationName}");
        }
        catch (Exception ex)
        {
            GetStore().LogActivity($"Failed to send endorsement: {ex.Message}");
        }
    });
}

int CountTrustedEndorsements(List<Dictionary<string, object?>> endorsements, string? endorsedPublicKey)
{
    if (string.IsNullOrEmpty(endorsedPublicKey))
        return 0;

    var fingerprint = Crypto.Fingerprint(endorsedPublicKey);
    var count = 0;
    var store = GetStore();

    foreach (var endorsementData in endorsements)
    {
        var endorserId = endorsementData.GetValueOrDefault("endorser_mip_identifier")?.ToString();
        if (string.IsNullOrEmpty(endorserId))
            continue;

        var conn = store.FindConnection(endorserId);
        if (conn == null || !conn.IsActive)
            continue;

        var endorsement = Endorsement.FromPayload(endorsementData);
        if (!endorsement.ValidFor(fingerprint))
            continue;
        if (!endorsement.VerifySignature(conn.PublicKey!))
            continue;

        count++;
    }

    return count;
}

void CheckPendingConnectionsForAutoApproval(IHttpClientFactory factory)
{
    var store = GetStore();
    var identity = GetIdentity();

    foreach (var connection in store.PendingConnections.ToList())
    {
        var endorsements = store.FindEndorsementsFor(connection.MipIdentifier);

        var trustedCount = endorsements.Count(e =>
        {
            var endorserConnection = store.FindConnection(e.EndorserMipIdentifier);
            if (endorserConnection == null || !endorserConnection.IsActive)
                return false;
            if (!e.ValidFor(connection.PublicKeyFingerprint!))
                return false;
            return e.VerifySignature(endorserConnection.PublicKey!);
        });

        if (trustedCount >= identity.TrustThreshold)
        {
            connection.Approve(dailyRateLimit: 100);
            store.LogActivity($"Auto-approved pending connection: {connection.OrganizationName}");

            Task.Run(async () =>
            {
                try
                {
                    var client = GetClient(factory);
                    await client.ApproveConnectionAsync(connection.MipUrl, identity.ToNodeProfile(), 100);
                    SendEndorsementToConnection(connection, factory);
                }
                catch { }
            });
        }
    }
}

// ============================================================================
// Admin Dashboard Routes (HTML)
// ============================================================================

app.MapGet("/", () =>
{
    var store = GetStore();
    var identity = GetIdentity();
    return Results.Content(GenerateDashboardHtml(store, identity), "text/html");
});

app.MapGet("/connections", () =>
{
    var store = GetStore();
    var identity = GetIdentity();
    return Results.Content(GenerateConnectionsHtml(store, identity), "text/html");
});

app.MapGet("/connections/{mipId}", (string mipId) =>
{
    var connection = GetStore().FindConnection(mipId);
    if (connection == null)
        return Results.NotFound("Connection not found");
    return Results.Content(GenerateConnectionDetailHtml(connection, GetIdentity()), "text/html");
});

app.MapPost("/connections", async (HttpRequest request, IHttpClientFactory factory) =>
{
    var form = await request.ReadFormAsync();
    var targetUrl = form["target_url"].ToString().Trim();

    if (string.IsNullOrEmpty(targetUrl))
        return Results.BadRequest("Target URL required");

    var store = GetStore();
    var identity = GetIdentity();
    var endorsements = store.FindEndorsementsFor(identity.MipIdentifier).ToList();

    try
    {
        var client = GetClient(factory);
        var result = await client.RequestConnectionAsync(targetUrl, endorsements);

        if (result.Success && result.Body.TryGetValue("meta", out var metaObj))
        {
            var meta = metaObj as JsonElement?;
            if (meta?.GetProperty("succeeded").GetBoolean() == true)
            {
                var data = result.Body["data"] as JsonElement?;
                var mipConnection = data?.GetProperty("mip_connection");
                var nodeProfile = mipConnection?.GetProperty("node_profile");

                var targetMipId = targetUrl.Split('/').Last();

                var connection = new Connection
                {
                    MipIdentifier = nodeProfile?.GetProperty("mip_identifier").GetString() ?? targetMipId,
                    MipUrl = targetUrl,
                    PublicKey = nodeProfile?.GetProperty("public_key").GetString(),
                    OrganizationName = nodeProfile?.GetProperty("organization_legal_name").GetString() ?? "Unknown",
                    ContactPerson = nodeProfile?.TryGetProperty("contact_person", out var cp) == true ? cp.GetString() : null,
                    ContactPhone = nodeProfile?.TryGetProperty("contact_phone", out var cph) == true ? cph.GetString() : null,
                    Status = mipConnection?.GetProperty("status").GetString() ?? "PENDING",
                    Direction = "outbound",
                    DailyRateLimit = mipConnection?.TryGetProperty("daily_rate_limit", out var drl) == true ? drl.GetInt32() : 100
                };
                store.AddConnection(connection);

                if (connection.IsActive)
                {
                    SendEndorsementToConnection(connection, factory);
                }

                return Results.Redirect("/connections");
            }
        }

        var errorMsg = "Connection request failed";
        return Results.BadRequest(errorMsg);
    }
    catch (Exception ex)
    {
        return Results.Problem($"Connection failed: {ex.Message}");
    }
});

app.MapPost("/connections/{mipId}/approve", async (string mipId, IHttpClientFactory factory) =>
{
    var store = GetStore();
    var connection = store.FindConnection(mipId);
    if (connection == null)
        return Results.NotFound("Connection not found");
    if (!connection.IsPending)
        return Results.BadRequest("Connection is not pending");

    connection.Approve(dailyRateLimit: 100);
    store.LogActivity($"Approved connection: {connection.OrganizationName}");

    try
    {
        var client = GetClient(factory);
        await client.ApproveConnectionAsync(connection.MipUrl, GetIdentity().ToNodeProfile(), 100);
        SendEndorsementToConnection(connection, factory);
    }
    catch (Exception ex)
    {
        store.LogActivity($"Failed to notify approval: {ex.Message}");
    }

    return Results.Redirect("/connections");
});

app.MapPost("/connections/{mipId}/decline", async (string mipId, HttpRequest request, IHttpClientFactory factory) =>
{
    var store = GetStore();
    var connection = store.FindConnection(mipId);
    if (connection == null)
        return Results.NotFound("Connection not found");
    if (!connection.IsPending)
        return Results.BadRequest("Connection is not pending");

    var form = await request.ReadFormAsync();
    var reason = form["reason"].ToString();

    connection.Decline(reason);
    store.LogActivity($"Declined connection: {connection.OrganizationName}");

    try
    {
        var client = GetClient(factory);
        await client.DeclineConnectionAsync(connection.MipUrl, reason);
    }
    catch (Exception ex)
    {
        store.LogActivity($"Failed to notify decline: {ex.Message}");
    }

    return Results.Redirect("/connections");
});

app.MapPost("/connections/{mipId}/revoke", async (string mipId, HttpRequest request, IHttpClientFactory factory) =>
{
    var store = GetStore();
    var connection = store.FindConnection(mipId);
    if (connection == null)
        return Results.NotFound("Connection not found");
    if (!connection.IsActive)
        return Results.BadRequest("Connection is not active");

    var form = await request.ReadFormAsync();
    var reason = form["reason"].ToString();

    connection.Revoke(reason);
    store.LogActivity($"Revoked connection: {connection.OrganizationName}");

    try
    {
        var client = GetClient(factory);
        await client.RevokeConnectionAsync(connection.MipUrl, reason);
    }
    catch (Exception ex)
    {
        store.LogActivity($"Failed to notify revoke: {ex.Message}");
    }

    return Results.Redirect("/connections");
});

app.MapPost("/connections/{mipId}/restore", async (string mipId, IHttpClientFactory factory) =>
{
    var store = GetStore();
    var connection = store.FindConnection(mipId);
    if (connection == null)
        return Results.NotFound("Connection not found");
    if (!connection.IsRevoked)
        return Results.BadRequest("Connection is not revoked");

    connection.Restore();
    store.LogActivity($"Restored connection: {connection.OrganizationName}");

    try
    {
        var client = GetClient(factory);
        await client.RestoreConnectionAsync(connection.MipUrl);
    }
    catch (Exception ex)
    {
        store.LogActivity($"Failed to notify restore: {ex.Message}");
    }

    return Results.Redirect("/connections");
});

app.MapGet("/members", () =>
{
    var store = GetStore();
    return Results.Content(GenerateMembersHtml(store, GetIdentity()), "text/html");
});

app.MapGet("/searches", () =>
{
    var store = GetStore();
    return Results.Content(GenerateSearchesHtml(store, GetIdentity()), "text/html");
});

app.MapGet("/searches/new", () =>
{
    var store = GetStore();
    return Results.Content(GenerateNewSearchHtml(store, GetIdentity()), "text/html");
});

app.MapPost("/searches", async (HttpRequest request, IHttpClientFactory factory) =>
{
    var form = await request.ReadFormAsync();
    var targetMipId = form["target_mip_id"].ToString();

    var store = GetStore();
    var connection = store.FindConnection(targetMipId);
    if (connection == null || !connection.IsActive)
        return Results.BadRequest("Invalid connection");

    var searchParams = new Dictionary<string, string?>();
    if (!string.IsNullOrEmpty(form["member_number"]))
        searchParams["member_number"] = form["member_number"].ToString();
    if (!string.IsNullOrEmpty(form["first_name"]))
        searchParams["first_name"] = form["first_name"].ToString();
    if (!string.IsNullOrEmpty(form["last_name"]))
        searchParams["last_name"] = form["last_name"].ToString();
    if (!string.IsNullOrEmpty(form["birthdate"]))
        searchParams["birthdate"] = form["birthdate"].ToString();

    if (searchParams.Count == 0)
        return Results.BadRequest("Search criteria required");

    var searchRequest = new SearchRequest
    {
        Direction = "outbound",
        TargetMipIdentifier = connection.MipIdentifier,
        TargetOrg = connection.OrganizationName,
        SearchParams = searchParams,
        Notes = form["notes"].ToString()
    };
    store.AddSearchRequest(searchRequest);

    try
    {
        var client = GetClient(factory);
        var result = await client.MemberSearchAsync(connection.MipUrl, searchRequest);
        if (result.Success)
        {
            store.LogActivity($"Search sent to {connection.OrganizationName}");
        }
    }
    catch (Exception ex)
    {
        store.LogActivity($"Search failed: {ex.Message}");
    }

    return Results.Redirect("/searches");
});

app.MapPost("/searches/{id}/approve", async (string id, IHttpClientFactory factory) =>
{
    var store = GetStore();
    var search = store.FindSearchRequest(id);
    if (search == null)
        return Results.NotFound("Search not found");
    if (!search.IsPending)
        return Results.BadRequest("Search is not pending");

    var matches = store.SearchMembers(search.SearchParams).Select(m => m.ToSearchResult()).ToList();
    search.Approve(matches);
    store.LogActivity($"Approved search from {search.TargetOrg}: {matches.Count} matches");

    var connection = store.FindConnection(search.TargetMipIdentifier);
    if (connection?.IsActive == true)
    {
        try
        {
            var client = GetClient(factory);
            await client.MemberSearchReplyAsync(connection.MipUrl, search);
        }
        catch (Exception ex)
        {
            store.LogActivity($"Failed to send search reply: {ex.Message}");
        }
    }

    return Results.Redirect("/searches");
});

app.MapPost("/searches/{id}/decline", async (string id, HttpRequest request, IHttpClientFactory factory) =>
{
    var store = GetStore();
    var search = store.FindSearchRequest(id);
    if (search == null)
        return Results.NotFound("Search not found");
    if (!search.IsPending)
        return Results.BadRequest("Search is not pending");

    var form = await request.ReadFormAsync();
    search.Decline(form["reason"].ToString());
    store.LogActivity($"Declined search from {search.TargetOrg}");

    var connection = store.FindConnection(search.TargetMipIdentifier);
    if (connection?.IsActive == true)
    {
        try
        {
            var client = GetClient(factory);
            await client.MemberSearchReplyAsync(connection.MipUrl, search);
        }
        catch (Exception ex)
        {
            store.LogActivity($"Failed to send search reply: {ex.Message}");
        }
    }

    return Results.Redirect("/searches");
});

app.MapGet("/cogs", () =>
{
    var store = GetStore();
    return Results.Content(GenerateCogsHtml(store, GetIdentity()), "text/html");
});

app.MapGet("/cogs/new", () =>
{
    var store = GetStore();
    return Results.Content(GenerateNewCogsHtml(store, GetIdentity()), "text/html");
});

app.MapPost("/cogs", async (HttpRequest request, IHttpClientFactory factory) =>
{
    var form = await request.ReadFormAsync();
    var targetMipId = form["target_mip_id"].ToString();

    var store = GetStore();
    var connection = store.FindConnection(targetMipId);
    if (connection == null || !connection.IsActive)
        return Results.BadRequest("Invalid connection");

    var cogs = new CogsRequest
    {
        Direction = "outbound",
        TargetMipIdentifier = connection.MipIdentifier,
        TargetOrg = connection.OrganizationName,
        RequestingMember = new Dictionary<string, string?>
        {
            ["member_number"] = form["requesting_member_number"].ToString(),
            ["first_name"] = form["requesting_first_name"].ToString(),
            ["last_name"] = form["requesting_last_name"].ToString()
        },
        RequestedMemberNumber = form["requested_member_number"].ToString(),
        Notes = form["notes"].ToString()
    };
    store.AddCogsRequest(cogs);

    try
    {
        var client = GetClient(factory);
        var result = await client.RequestCogsAsync(connection.MipUrl, cogs);
        if (result.Success)
        {
            store.LogActivity($"COGS requested from {connection.OrganizationName}");
        }
    }
    catch (Exception ex)
    {
        store.LogActivity($"COGS request failed: {ex.Message}");
    }

    return Results.Redirect("/cogs");
});

app.MapPost("/cogs/{id}/approve", async (string id, IHttpClientFactory factory) =>
{
    var store = GetStore();
    var identity = GetIdentity();
    var cogs = store.FindCogsRequest(id);
    if (cogs == null)
        return Results.NotFound("COGS not found");
    if (!cogs.IsPending)
        return Results.BadRequest("COGS is not pending");

    var member = store.FindMember(cogs.RequestedMemberNumber);
    if (member == null)
        return Results.BadRequest("Member not found");

    var issuingOrg = new Dictionary<string, object?>
    {
        ["mip_identifier"] = identity.MipIdentifier,
        ["organization_legal_name"] = identity.OrganizationName
    };
    cogs.Approve(member, issuingOrg);
    store.LogActivity($"Approved COGS for {member.MemberNumber}");

    var connection = store.FindConnection(cogs.TargetMipIdentifier);
    if (connection?.IsActive == true)
    {
        try
        {
            var client = GetClient(factory);
            await client.CogsReplyAsync(connection.MipUrl, cogs);
        }
        catch (Exception ex)
        {
            store.LogActivity($"Failed to send COGS reply: {ex.Message}");
        }
    }

    return Results.Redirect("/cogs");
});

app.MapPost("/cogs/{id}/decline", async (string id, HttpRequest request, IHttpClientFactory factory) =>
{
    var store = GetStore();
    var cogs = store.FindCogsRequest(id);
    if (cogs == null)
        return Results.NotFound("COGS not found");
    if (!cogs.IsPending)
        return Results.BadRequest("COGS is not pending");

    var form = await request.ReadFormAsync();
    cogs.Decline(form["reason"].ToString());
    store.LogActivity("Declined COGS request");

    var connection = store.FindConnection(cogs.TargetMipIdentifier);
    if (connection?.IsActive == true)
    {
        try
        {
            var client = GetClient(factory);
            await client.CogsReplyAsync(connection.MipUrl, cogs);
        }
        catch (Exception ex)
        {
            store.LogActivity($"Failed to send COGS reply: {ex.Message}");
        }
    }

    return Results.Redirect("/cogs");
});

// ============================================================================
// MIP Protocol Endpoints
// ============================================================================

// Enable request body buffering for signature verification
app.Use(async (context, next) =>
{
    context.Request.EnableBuffering();
    await next();
});

// Connection request
app.MapPost("/mip/node/{mipId}/mip_connections", async (string mipId, HttpRequest request, IHttpClientFactory factory) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, publicKey, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    var store = GetStore();
    var identity = GetIdentity();
    var jsonBody = JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new();

    // Check if connection already exists
    var requestMipId = jsonBody.GetValueOrDefault("mip_identifier")?.ToString();
    var existing = requestMipId != null ? store.FindConnection(requestMipId) : null;
    if (existing != null)
    {
        return MipResponse(true, new
        {
            mip_connection = new
            {
                status = existing.Status,
                daily_rate_limit = existing.DailyRateLimit,
                node_profile = identity.ToNodeProfile()
            }
        });
    }

    // Create new connection from request
    var newConnection = Connection.FromRequest(jsonBody, direction: "inbound");

    // Check for auto-approval via web-of-trust
    var endorsements = new List<Dictionary<string, object?>>();
    if (jsonBody.TryGetValue("endorsements", out var endorsementsObj) && endorsementsObj is JsonElement endorsementsArray)
    {
        foreach (var elem in endorsementsArray.EnumerateArray())
        {
            var dict = JsonSerializer.Deserialize<Dictionary<string, object?>>(elem.GetRawText());
            if (dict != null)
                endorsements.Add(dict);
        }
    }

    var trustedCount = CountTrustedEndorsements(endorsements, newConnection.PublicKey);

    if (trustedCount >= identity.TrustThreshold)
    {
        newConnection.Approve(dailyRateLimit: 100);
        store.AddConnection(newConnection);
        store.LogActivity($"Auto-approved connection: {newConnection.OrganizationName} ({trustedCount} trusted endorsements)");

        SendEndorsementToConnection(newConnection, factory);

        return MipResponse(true, new
        {
            mip_connection = new
            {
                status = "ACTIVE",
                daily_rate_limit = 100,
                node_profile = identity.ToNodeProfile()
            }
        });
    }
    else
    {
        store.AddConnection(newConnection);
        store.LogActivity($"Connection request from: {newConnection.OrganizationName}");

        return MipResponse(true, new
        {
            mip_connection = new
            {
                status = "PENDING",
                daily_rate_limit = 100,
                node_profile = identity.ToNodeProfile()
            }
        });
    }
});

// Connection approved notification
app.MapPost("/mip/node/{mipId}/mip_connections/approved", async (string mipId, HttpRequest request, IHttpClientFactory factory) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, _, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    var store = GetStore();
    connection = store.FindConnection(senderMipId!);
    if (connection == null)
        return MipResponse(false, new { error = "Connection not found" });

    var jsonBody = JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new();

    Dictionary<string, object?>? nodeProfile = null;
    if (jsonBody.TryGetValue("node_profile", out var npObj) && npObj is JsonElement npElem)
    {
        nodeProfile = JsonSerializer.Deserialize<Dictionary<string, object?>>(npElem.GetRawText());
    }

    var dailyRateLimit = 100;
    if (jsonBody.TryGetValue("daily_rate_limit", out var drlObj) && drlObj is JsonElement drlElem)
    {
        dailyRateLimit = drlElem.GetInt32();
    }

    connection.Approve(nodeProfile, dailyRateLimit);
    store.LogActivity($"Connection approved by: {connection.OrganizationName}");

    SendEndorsementToConnection(connection, factory);

    return MipResponse(true, new { mip_connection = new { status = "ACTIVE" } });
});

// Connection declined notification
app.MapPost("/mip/node/{mipId}/mip_connections/declined", async (string mipId, HttpRequest request) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, _, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    var store = GetStore();
    connection = store.FindConnection(senderMipId!);
    if (connection == null)
        return MipResponse(false, new { error = "Connection not found" });

    var jsonBody = JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new();
    var reason = jsonBody.GetValueOrDefault("reason")?.ToString();

    connection.Decline(reason);
    store.LogActivity($"Connection declined by: {connection.OrganizationName}");

    return MipResponse(true, new { mip_connection = new { status = "DECLINED" } });
});

// Connection revoke notification
app.MapPost("/mip/node/{mipId}/mip_connections/revoke", async (string mipId, HttpRequest request) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, _, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    var store = GetStore();
    connection = store.FindConnection(senderMipId!);
    if (connection == null)
        return MipResponse(false, new { error = "Connection not found" });

    var jsonBody = JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new();
    var reason = jsonBody.GetValueOrDefault("reason")?.ToString();

    connection.Revoke(reason);
    store.LogActivity($"Connection revoked by: {connection.OrganizationName}");

    return MipResponse(true, new { mip_connection = new { status = "REVOKED" } });
});

// Connection restore notification
app.MapPost("/mip/node/{mipId}/mip_connections/restore", async (string mipId, HttpRequest request) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, _, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    var store = GetStore();
    connection = store.FindConnection(senderMipId!);
    if (connection == null)
        return MipResponse(false, new { error = "Connection not found" });

    connection.Restore();
    store.LogActivity($"Connection restored by: {connection.OrganizationName}");

    return MipResponse(true, new { mip_connection = new { status = "ACTIVE" } });
});

// Receive endorsement
app.MapPost("/mip/node/{mipId}/endorsements", async (string mipId, HttpRequest request, IHttpClientFactory factory) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, _, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    if (connection == null || !connection.IsActive)
        return MipResponse(false, new { error = "No active connection" });

    var store = GetStore();
    var jsonBody = JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new();
    var endorsement = Endorsement.FromPayload(jsonBody);

    if (!endorsement.VerifySignature(connection.PublicKey!))
        return MipResponse(false, new { error = "Invalid endorsement signature" });

    store.AddEndorsement(endorsement);

    CheckPendingConnectionsForAutoApproval(factory);

    return MipResponse(true, new { endorsement_id = endorsement.Id });
});

// Query connected organizations
app.MapGet("/mip/node/{mipId}/connected_organizations_query", (string mipId, HttpRequest request) =>
{
    var (senderMipId, connection, _, error) = VerifyMipRequest(request, "");
    if (error != null)
        return MipResponse(false, new { error });

    if (connection == null || !connection.IsActive)
        return MipResponse(false, new { error = "No active connection" });

    var store = GetStore();
    var shareableOrgs = store.ActiveConnections
        .Where(c => c.ShareMyOrganization && c.MipIdentifier != senderMipId)
        .Select(c => c.ToNodeProfile())
        .ToList();

    return MipResponse(true, new { organizations = shareableOrgs });
});

// Member search request
app.MapPost("/mip/node/{mipId}/mip_member_searches", async (string mipId, HttpRequest request) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, _, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    if (connection == null || !connection.IsActive)
        return MipResponse(false, new { error = "No active connection" });

    var store = GetStore();
    var jsonBody = JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new();
    var search = SearchRequest.FromRequest(jsonBody, senderMipId!, connection.OrganizationName);
    store.AddSearchRequest(search);

    return MipResponse(true, new { status = "PENDING", shared_identifier = search.SharedIdentifier });
});

// Member search reply
app.MapPost("/mip/node/{mipId}/mip_member_searches/reply", async (string mipId, HttpRequest request) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, _, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    if (connection == null || !connection.IsActive)
        return MipResponse(false, new { error = "No active connection" });

    var store = GetStore();
    var jsonBody = JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new();

    Dictionary<string, object?>? data = jsonBody;
    if (jsonBody.TryGetValue("data", out var dataObj) && dataObj is JsonElement dataElem)
    {
        data = JsonSerializer.Deserialize<Dictionary<string, object?>>(dataElem.GetRawText());
    }

    var sharedId = data?.GetValueOrDefault("shared_identifier")?.ToString();
    var search = sharedId != null ? store.FindSearchRequest(sharedId) : null;

    if (search != null)
    {
        var status = data?.GetValueOrDefault("status")?.ToString();
        if (status == "APPROVED")
        {
            var matches = new List<Dictionary<string, object?>>();
            if (data?.TryGetValue("matches", out var matchesObj) == true && matchesObj is JsonElement matchesArray)
            {
                foreach (var elem in matchesArray.EnumerateArray())
                {
                    var dict = JsonSerializer.Deserialize<Dictionary<string, object?>>(elem.GetRawText());
                    if (dict != null)
                        matches.Add(dict);
                }
            }
            search.Approve(matches);
            store.LogActivity($"Search results received: {search.Matches.Count} matches");
        }
        else
        {
            var reason = data?.GetValueOrDefault("reason")?.ToString();
            search.Decline(reason);
            store.LogActivity("Search declined");
        }
    }

    return MipResponse(true, new { acknowledged = true });
});

// Member status check (real-time)
app.MapPost("/mip/node/{mipId}/member_status_checks", async (string mipId, HttpRequest request) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, _, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    if (connection == null || !connection.IsActive)
        return MipResponse(false, new { error = "No active connection" });

    var store = GetStore();
    var jsonBody = JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new();
    var memberNumber = jsonBody.GetValueOrDefault("member_number")?.ToString();

    var member = memberNumber != null ? store.FindMember(memberNumber) : null;
    if (member != null)
    {
        return MipResponse(true, member.ToStatusCheck());
    }
    else
    {
        return MipResponse(true, new { found = false, member_number = memberNumber });
    }
});

// COGS request
app.MapPost("/mip/node/{mipId}/certificates_of_good_standing", async (string mipId, HttpRequest request) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, _, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    if (connection == null || !connection.IsActive)
        return MipResponse(false, new { error = "No active connection" });

    var store = GetStore();
    var jsonBody = JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new();
    var cogs = CogsRequest.FromRequest(jsonBody, senderMipId!, connection.OrganizationName);
    store.AddCogsRequest(cogs);

    return MipResponse(true, new { status = "PENDING", shared_identifier = cogs.SharedIdentifier });
});

// COGS reply
app.MapPost("/mip/node/{mipId}/certificates_of_good_standing/reply", async (string mipId, HttpRequest request) =>
{
    request.Body.Seek(0, SeekOrigin.Begin);
    using var reader = new StreamReader(request.Body, Encoding.UTF8, leaveOpen: true);
    var body = await reader.ReadToEndAsync();

    var (senderMipId, connection, _, error) = VerifyMipRequest(request, body);
    if (error != null)
        return MipResponse(false, new { error });

    if (connection == null || !connection.IsActive)
        return MipResponse(false, new { error = "No active connection" });

    var store = GetStore();
    var jsonBody = JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new();

    var sharedId = jsonBody.GetValueOrDefault("shared_identifier")?.ToString();
    var cogs = sharedId != null ? store.FindCogsRequest(sharedId) : null;

    if (cogs != null)
    {
        var status = jsonBody.GetValueOrDefault("status")?.ToString();
        if (status == "APPROVED")
        {
            cogs.Status = "APPROVED";
            cogs.Certificate = jsonBody;
            var memberNumber = "";
            if (jsonBody.TryGetValue("member_profile", out var mpObj) && mpObj is JsonElement mpElem)
            {
                memberNumber = mpElem.TryGetProperty("member_number", out var mnProp) ? mnProp.GetString() : "";
            }
            store.LogActivity($"COGS received for {memberNumber}");
        }
        else
        {
            cogs.Status = "DECLINED";
            cogs.DeclineReason = jsonBody.GetValueOrDefault("reason")?.ToString();
            store.LogActivity($"COGS declined: {cogs.DeclineReason}");
        }
    }

    return MipResponse(true, new { acknowledged = true, shared_identifier = sharedId });
});

app.Run();

// ============================================================================
// Initialization
// ============================================================================

void InitializeNode(string[] args)
{
    var configFile = Environment.GetEnvironmentVariable("CONFIG") ?? "config/node1.yaml";
    var configPath = Path.Combine(AppContext.BaseDirectory, configFile);

    if (!File.Exists(configPath))
    {
        configPath = Path.Combine(Directory.GetCurrentDirectory(), configFile);
    }

    var yamlContent = File.ReadAllText(configPath);
    var deserializer = new DeserializerBuilder()
        .WithNamingConvention(UnderscoredNamingConvention.Instance)
        .Build();
    var config = deserializer.Deserialize<NodeConfig>(yamlContent);

    Store.Reset();
    var store = Store.Current;

    var identity = NodeIdentity.FromConfig(config, config.Port);
    store.SetNodeIdentity(identity);

    foreach (var memberConfig in config.Members)
    {
        var member = Member.FromConfig(memberConfig);
        store.AddMember(member);
    }

    store.LogActivity($"Node initialized: {identity.OrganizationName}");

    Console.WriteLine(new string('=', 60));
    Console.WriteLine($"MIP Node Started: {identity.OrganizationName}");
    Console.WriteLine($"MIP Identifier: {identity.MipIdentifier}");
    Console.WriteLine($"MIP URL: {identity.MipUrl}");
    Console.WriteLine($"Public Key Fingerprint: {identity.PublicKeyFingerprint}");
    Console.WriteLine($"Members loaded: {store.AllMembers.Count()}");
    Console.WriteLine(new string('=', 60));
}

// ============================================================================
// HTML Generation (simple templates)
// ============================================================================

string GenerateLayout(string title, string content, NodeIdentity identity)
{
    return $@"<!DOCTYPE html>
<html>
<head>
    <title>{title} - MIP Reference</title>
    <link rel=""stylesheet"" href=""/style.css"">
</head>
<body>
    <nav>
        <h1>{identity.OrganizationName}</h1>
        <ul>
            <li><a href=""/"">Dashboard</a></li>
            <li><a href=""/connections"">Connections</a></li>
            <li><a href=""/members"">Members</a></li>
            <li><a href=""/searches"">Searches</a></li>
            <li><a href=""/cogs"">COGS</a></li>
        </ul>
    </nav>
    <main>
        {content}
    </main>
</body>
</html>";
}

string GenerateDashboardHtml(Store store, NodeIdentity identity)
{
    var activities = string.Join("\n", store.RecentActivity(10).Select(a =>
        $"<li><small>{a.Timestamp}</small> {a.Message}</li>"));

    var content = $@"
<h2>Dashboard</h2>
<div class=""info-box"">
    <p><strong>MIP Identifier:</strong> {identity.MipIdentifier}</p>
    <p><strong>MIP URL:</strong> {identity.MipUrl}</p>
    <p><strong>Public Key Fingerprint:</strong> {identity.PublicKeyFingerprint}</p>
</div>
<div class=""stats"">
    <div class=""stat"">
        <h3>{store.ActiveConnections.Count()}</h3>
        <p>Active Connections</p>
    </div>
    <div class=""stat"">
        <h3>{store.PendingConnections.Count()}</h3>
        <p>Pending Connections</p>
    </div>
    <div class=""stat"">
        <h3>{store.AllMembers.Count()}</h3>
        <p>Members</p>
    </div>
</div>
<h3>Recent Activity</h3>
<ul class=""activity"">{activities}</ul>";

    return GenerateLayout("Dashboard", content, identity);
}

string GenerateConnectionsHtml(Store store, NodeIdentity identity)
{
    var connections = string.Join("\n", store.AllConnections.Select(c => $@"
<tr>
    <td><a href=""/connections/{c.MipIdentifier}"">{c.OrganizationName}</a></td>
    <td>{c.Status}</td>
    <td>{c.Direction}</td>
    <td>{c.CreatedAt}</td>
</tr>"));

    var content = $@"
<h2>Connections</h2>
<form method=""post"" action=""/connections"" class=""inline-form"">
    <input type=""text"" name=""target_url"" placeholder=""Target MIP URL (e.g., http://localhost:4002/mip/node/abc123)"" required>
    <button type=""submit"">Request Connection</button>
</form>
<table>
    <thead>
        <tr>
            <th>Organization</th>
            <th>Status</th>
            <th>Direction</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>{connections}</tbody>
</table>";

    return GenerateLayout("Connections", content, identity);
}

string GenerateConnectionDetailHtml(Connection connection, NodeIdentity identity)
{
    var actions = "";
    if (connection.IsPending && connection.IsInbound)
    {
        actions = @"
<form method=""post"" action=""/connections/" + connection.MipIdentifier + @"/approve"" style=""display:inline"">
    <button type=""submit"">Approve</button>
</form>
<form method=""post"" action=""/connections/" + connection.MipIdentifier + @"/decline"" style=""display:inline"">
    <input type=""text"" name=""reason"" placeholder=""Reason"">
    <button type=""submit"">Decline</button>
</form>";
    }
    else if (connection.IsActive)
    {
        actions = @"
<form method=""post"" action=""/connections/" + connection.MipIdentifier + @"/revoke"" style=""display:inline"">
    <input type=""text"" name=""reason"" placeholder=""Reason"">
    <button type=""submit"">Revoke</button>
</form>";
    }
    else if (connection.IsRevoked)
    {
        actions = @"
<form method=""post"" action=""/connections/" + connection.MipIdentifier + @"/restore"" style=""display:inline"">
    <button type=""submit"">Restore</button>
</form>";
    }

    var content = $@"
<h2>{connection.OrganizationName}</h2>
<div class=""info-box"">
    <p><strong>MIP Identifier:</strong> {connection.MipIdentifier}</p>
    <p><strong>MIP URL:</strong> {connection.MipUrl}</p>
    <p><strong>Status:</strong> {connection.Status}</p>
    <p><strong>Direction:</strong> {connection.Direction}</p>
    <p><strong>Contact:</strong> {connection.ContactPerson} ({connection.ContactPhone})</p>
    <p><strong>Public Key Fingerprint:</strong> {connection.PublicKeyFingerprint}</p>
</div>
<div class=""actions"">{actions}</div>
<p><a href=""/connections"">Back to Connections</a></p>";

    return GenerateLayout(connection.OrganizationName, content, identity);
}

string GenerateMembersHtml(Store store, NodeIdentity identity)
{
    var members = string.Join("\n", store.AllMembers.Select(m => $@"
<tr>
    <td>{m.MemberNumber}</td>
    <td>{m.FullName}</td>
    <td>{m.Status}</td>
    <td>{(m.GoodStanding ? "Yes" : "No")}</td>
</tr>"));

    var content = $@"
<h2>Members</h2>
<table>
    <thead>
        <tr>
            <th>Member #</th>
            <th>Name</th>
            <th>Status</th>
            <th>Good Standing</th>
        </tr>
    </thead>
    <tbody>{members}</tbody>
</table>";

    return GenerateLayout("Members", content, identity);
}

string GenerateSearchesHtml(Store store, NodeIdentity identity)
{
    var searches = string.Join("\n", store.AllSearchRequests.Select(s => $@"
<tr>
    <td>{s.Direction}</td>
    <td>{s.TargetOrg}</td>
    <td>{s.SearchDescription}</td>
    <td>{s.Status}</td>
    <td>{s.Matches.Count}</td>
    <td>
        {(s.IsPending && s.IsInbound ? $@"
        <form method=""post"" action=""/searches/{s.SharedIdentifier}/approve"" style=""display:inline"">
            <button type=""submit"">Approve</button>
        </form>
        <form method=""post"" action=""/searches/{s.SharedIdentifier}/decline"" style=""display:inline"">
            <button type=""submit"">Decline</button>
        </form>" : "")}
    </td>
</tr>"));

    var content = $@"
<h2>Member Searches</h2>
<p><a href=""/searches/new"">New Search</a></p>
<table>
    <thead>
        <tr>
            <th>Direction</th>
            <th>Organization</th>
            <th>Search</th>
            <th>Status</th>
            <th>Matches</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>{searches}</tbody>
</table>";

    return GenerateLayout("Searches", content, identity);
}

string GenerateNewSearchHtml(Store store, NodeIdentity identity)
{
    var options = string.Join("\n", store.ActiveConnections.Select(c =>
        $"<option value=\"{c.MipIdentifier}\">{c.OrganizationName}</option>"));

    var content = $@"
<h2>New Member Search</h2>
<form method=""post"" action=""/searches"">
    <label>Target Organization:
        <select name=""target_mip_id"" required>
            <option value="""">Select...</option>
            {options}
        </select>
    </label>
    <fieldset>
        <legend>Search by Member Number</legend>
        <label>Member Number: <input type=""text"" name=""member_number""></label>
    </fieldset>
    <fieldset>
        <legend>Or Search by Name</legend>
        <label>First Name: <input type=""text"" name=""first_name""></label>
        <label>Last Name: <input type=""text"" name=""last_name""></label>
        <label>Birthdate: <input type=""date"" name=""birthdate""></label>
    </fieldset>
    <label>Notes: <textarea name=""notes""></textarea></label>
    <button type=""submit"">Submit Search</button>
</form>
<p><a href=""/searches"">Back to Searches</a></p>";

    return GenerateLayout("New Search", content, identity);
}

string GenerateCogsHtml(Store store, NodeIdentity identity)
{
    var cogs = string.Join("\n", store.AllCogsRequests.Select(c => $@"
<tr>
    <td>{c.Direction}</td>
    <td>{c.TargetOrg}</td>
    <td>{c.RequestedMemberNumber}</td>
    <td>{c.Status}</td>
    <td>
        {(c.IsPending && c.IsInbound ? $@"
        <form method=""post"" action=""/cogs/{c.SharedIdentifier}/approve"" style=""display:inline"">
            <button type=""submit"">Approve</button>
        </form>
        <form method=""post"" action=""/cogs/{c.SharedIdentifier}/decline"" style=""display:inline"">
            <button type=""submit"">Decline</button>
        </form>" : "")}
    </td>
</tr>"));

    var content = $@"
<h2>Certificates of Good Standing</h2>
<p><a href=""/cogs/new"">Request COGS</a></p>
<table>
    <thead>
        <tr>
            <th>Direction</th>
            <th>Organization</th>
            <th>Member #</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>{cogs}</tbody>
</table>";

    return GenerateLayout("COGS", content, identity);
}

string GenerateNewCogsHtml(Store store, NodeIdentity identity)
{
    var options = string.Join("\n", store.ActiveConnections.Select(c =>
        $"<option value=\"{c.MipIdentifier}\">{c.OrganizationName}</option>"));

    var content = $@"
<h2>Request Certificate of Good Standing</h2>
<form method=""post"" action=""/cogs"">
    <label>Target Organization:
        <select name=""target_mip_id"" required>
            <option value="""">Select...</option>
            {options}
        </select>
    </label>
    <fieldset>
        <legend>Requesting Member (Your Organization)</legend>
        <label>Member Number: <input type=""text"" name=""requesting_member_number"" required></label>
        <label>First Name: <input type=""text"" name=""requesting_first_name"" required></label>
        <label>Last Name: <input type=""text"" name=""requesting_last_name"" required></label>
    </fieldset>
    <fieldset>
        <legend>Requested Member (Their Organization)</legend>
        <label>Member Number: <input type=""text"" name=""requested_member_number"" required></label>
    </fieldset>
    <label>Notes: <textarea name=""notes""></textarea></label>
    <button type=""submit"">Request COGS</button>
</form>
<p><a href=""/cogs"">Back to COGS</a></p>";

    return GenerateLayout("Request COGS", content, identity);
}
