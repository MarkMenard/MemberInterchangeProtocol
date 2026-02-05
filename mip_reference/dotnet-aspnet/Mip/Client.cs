using System.Net.Http.Json;
using System.Text;
using System.Text.Json;
using Mip.Models;

namespace Mip;

/// <summary>
/// HTTP client for making outbound MIP requests.
/// </summary>
public class MipClient
{
    private readonly NodeIdentity _identity;
    private readonly HttpClient _httpClient;

    public MipClient(NodeIdentity identity, HttpClient? httpClient = null)
    {
        _identity = identity;
        _httpClient = httpClient ?? new HttpClient { Timeout = TimeSpan.FromSeconds(30) };
    }

    /// <summary>
    /// Request a connection with another node.
    /// </summary>
    public async Task<MipResponse> RequestConnectionAsync(string targetUrl, List<Endorsement>? endorsements = null)
    {
        var payload = new Dictionary<string, object?>
        {
            ["mip_identifier"] = _identity.MipIdentifier,
            ["mip_url"] = _identity.MipUrl,
            ["public_key"] = _identity.PublicKey,
            ["organization_legal_name"] = _identity.OrganizationName,
            ["contact_person"] = _identity.ContactPerson,
            ["contact_phone"] = _identity.ContactPhone,
            ["share_my_organization"] = _identity.ShareMyOrganization,
            ["endorsements"] = endorsements?.Select(e => e.ToPayload()).ToList() ?? new List<Dictionary<string, object?>>()
        };

        return await PostRequestAsync(BuildUrl(targetUrl, "/mip_connections"), payload, includePublicKey: true);
    }

    /// <summary>
    /// Notify a node their connection request was approved.
    /// </summary>
    public async Task<MipResponse> ApproveConnectionAsync(string targetUrl, Dictionary<string, object?> nodeProfile, int dailyRateLimit = 100)
    {
        var payload = new Dictionary<string, object?>
        {
            ["node_profile"] = nodeProfile,
            ["share_my_organization"] = _identity.ShareMyOrganization,
            ["daily_rate_limit"] = dailyRateLimit
        };

        return await PostRequestAsync(BuildUrl(targetUrl, "/mip_connections/approved"), payload);
    }

    /// <summary>
    /// Notify a node their connection request was declined.
    /// </summary>
    public async Task<MipResponse> DeclineConnectionAsync(string targetUrl, string? reason = null)
    {
        var payload = new Dictionary<string, object?>
        {
            ["mip_identifier"] = _identity.MipIdentifier,
            ["reason"] = reason
        };

        return await PostRequestAsync(BuildUrl(targetUrl, "/mip_connections/declined"), payload);
    }

    /// <summary>
    /// Notify a node their connection has been revoked.
    /// </summary>
    public async Task<MipResponse> RevokeConnectionAsync(string targetUrl, string? reason = null)
    {
        var payload = new Dictionary<string, object?>
        {
            ["mip_identifier"] = _identity.MipIdentifier,
            ["reason"] = reason
        };

        return await PostRequestAsync(BuildUrl(targetUrl, "/mip_connections/revoke"), payload);
    }

    /// <summary>
    /// Notify a node their connection has been restored.
    /// </summary>
    public async Task<MipResponse> RestoreConnectionAsync(string targetUrl)
    {
        var payload = new Dictionary<string, object?>
        {
            ["mip_identifier"] = _identity.MipIdentifier
        };

        return await PostRequestAsync(BuildUrl(targetUrl, "/mip_connections/restore"), payload);
    }

    /// <summary>
    /// Send an endorsement to another node.
    /// </summary>
    public async Task<MipResponse> SendEndorsementAsync(string targetUrl, Endorsement endorsement)
    {
        return await PostRequestAsync(BuildUrl(targetUrl, "/endorsements"), endorsement.ToPayload());
    }

    /// <summary>
    /// Send a member search request.
    /// </summary>
    public async Task<MipResponse> MemberSearchAsync(string targetUrl, SearchRequest searchRequest)
    {
        return await PostRequestAsync(BuildUrl(targetUrl, "/mip_member_searches"), searchRequest.ToRequestPayload());
    }

    /// <summary>
    /// Send member search results back to requester.
    /// </summary>
    public async Task<MipResponse> MemberSearchReplyAsync(string targetUrl, SearchRequest searchRequest)
    {
        var payload = new Dictionary<string, object?>
        {
            ["meta"] = new Dictionary<string, object?> { ["succeeded"] = true },
            ["data"] = searchRequest.ToReplyPayload()
        };

        return await PostRequestAsync(BuildUrl(targetUrl, "/mip_member_searches/reply"), payload);
    }

    /// <summary>
    /// Request a Certificate of Good Standing.
    /// </summary>
    public async Task<MipResponse> RequestCogsAsync(string targetUrl, CogsRequest cogsRequest)
    {
        return await PostRequestAsync(BuildUrl(targetUrl, "/certificates_of_good_standing"), cogsRequest.ToRequestPayload());
    }

    /// <summary>
    /// Send COGS reply back to requester.
    /// </summary>
    public async Task<MipResponse> CogsReplyAsync(string targetUrl, CogsRequest cogsRequest)
    {
        return await PostRequestAsync(BuildUrl(targetUrl, "/certificates_of_good_standing/reply"), cogsRequest.ToReplyPayload());
    }

    /// <summary>
    /// Query connected organizations.
    /// </summary>
    public async Task<MipResponse> ConnectedOrganizationsQueryAsync(string targetUrl)
    {
        return await GetRequestAsync(BuildUrl(targetUrl, "/connected_organizations_query"));
    }

    private async Task<MipResponse> PostRequestAsync(string url, object payload, bool includePublicKey = false)
    {
        var timestamp = DateTime.UtcNow.ToString("o");
        var path = new Uri(url).AbsolutePath;
        var jsonBody = JsonSerializer.Serialize(payload);
        var signature = Signature.SignRequest(_identity.PrivateKey, timestamp, path, jsonBody);

        var request = new HttpRequestMessage(HttpMethod.Post, url)
        {
            Content = new StringContent(jsonBody, Encoding.UTF8, "application/json")
        };

        request.Headers.Add("X-MIP-MIP-IDENTIFIER", _identity.MipIdentifier);
        request.Headers.Add("X-MIP-TIMESTAMP", timestamp);
        request.Headers.Add("X-MIP-SIGNATURE", signature);

        if (includePublicKey)
        {
            request.Headers.Add("X-MIP-PUBLIC-KEY", Convert.ToBase64String(Encoding.UTF8.GetBytes(_identity.PublicKey)));
        }

        try
        {
            var response = await _httpClient.SendAsync(request);
            var body = await response.Content.ReadAsStringAsync();

            return new MipResponse
            {
                Success = response.IsSuccessStatusCode,
                Status = (int)response.StatusCode,
                Body = string.IsNullOrEmpty(body) ? new Dictionary<string, object?>() :
                    JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new Dictionary<string, object?>()
            };
        }
        catch (Exception ex)
        {
            return new MipResponse
            {
                Success = false,
                Status = 0,
                Body = new Dictionary<string, object?> { ["error"] = ex.Message }
            };
        }
    }

    private async Task<MipResponse> GetRequestAsync(string url)
    {
        var timestamp = DateTime.UtcNow.ToString("o");
        var path = new Uri(url).AbsolutePath;
        var signature = Signature.SignRequest(_identity.PrivateKey, timestamp, path);

        var request = new HttpRequestMessage(HttpMethod.Get, url);
        request.Headers.Add("X-MIP-MIP-IDENTIFIER", _identity.MipIdentifier);
        request.Headers.Add("X-MIP-TIMESTAMP", timestamp);
        request.Headers.Add("X-MIP-SIGNATURE", signature);

        try
        {
            var response = await _httpClient.SendAsync(request);
            var body = await response.Content.ReadAsStringAsync();

            return new MipResponse
            {
                Success = response.IsSuccessStatusCode,
                Status = (int)response.StatusCode,
                Body = string.IsNullOrEmpty(body) ? new Dictionary<string, object?>() :
                    JsonSerializer.Deserialize<Dictionary<string, object?>>(body) ?? new Dictionary<string, object?>()
            };
        }
        catch (Exception ex)
        {
            return new MipResponse
            {
                Success = false,
                Status = 0,
                Body = new Dictionary<string, object?> { ["error"] = ex.Message }
            };
        }
    }

    private static string BuildUrl(string baseUrl, string endpoint)
    {
        return baseUrl.TrimEnd('/') + endpoint;
    }
}

public class MipResponse
{
    public bool Success { get; set; }
    public int Status { get; set; }
    public Dictionary<string, object?> Body { get; set; } = new();
}
