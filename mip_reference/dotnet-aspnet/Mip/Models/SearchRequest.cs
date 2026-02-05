using System.Text.Json.Serialization;

namespace Mip.Models;

/// <summary>
/// Tracks member search requests (inbound and outbound).
/// </summary>
public class SearchRequest
{
    public static readonly string[] Statuses = { "PENDING", "APPROVED", "DECLINED" };

    public string SharedIdentifier { get; set; } = Guid.NewGuid().ToString();
    public string Direction { get; set; } = ""; // "inbound" or "outbound"
    public string TargetMipIdentifier { get; set; } = "";
    public string TargetOrg { get; set; } = "";
    public Dictionary<string, string?> SearchParams { get; set; } = new();
    public string? Notes { get; set; }
    public List<object> Documents { get; set; } = new();
    public string Status { get; set; } = "PENDING";
    public List<Dictionary<string, object?>> Matches { get; set; } = new();
    public string? DeclineReason { get; set; }
    public string CreatedAt { get; set; } = DateTime.UtcNow.ToString("o");

    [JsonIgnore]
    public bool IsPending => Status == "PENDING";
    [JsonIgnore]
    public bool IsApproved => Status == "APPROVED";
    [JsonIgnore]
    public bool IsDeclined => Status == "DECLINED";
    [JsonIgnore]
    public bool IsInbound => Direction == "inbound";
    [JsonIgnore]
    public bool IsOutbound => Direction == "outbound";

    public void Approve(List<Dictionary<string, object?>> matches)
    {
        Status = "APPROVED";
        Matches = matches;
    }

    public void Decline(string? reason = null)
    {
        Status = "DECLINED";
        DeclineReason = reason;
    }

    public string SearchDescription
    {
        get
        {
            if (SearchParams.TryGetValue("member_number", out var memberNum) && !string.IsNullOrEmpty(memberNum))
                return $"Member #{memberNum}";

            if (SearchParams.TryGetValue("first_name", out var firstName) &&
                SearchParams.TryGetValue("last_name", out var lastName) &&
                !string.IsNullOrEmpty(firstName) && !string.IsNullOrEmpty(lastName))
            {
                var name = $"{firstName} {lastName}";
                if (SearchParams.TryGetValue("birthdate", out var birthdate) && !string.IsNullOrEmpty(birthdate))
                    name += $" ({birthdate})";
                return name;
            }

            return "Unknown search";
        }
    }

    public Dictionary<string, object?> ToRequestPayload()
    {
        var payload = new Dictionary<string, object?>
        {
            ["shared_identifier"] = SharedIdentifier
        };

        if (SearchParams.TryGetValue("member_number", out var memberNum) && !string.IsNullOrEmpty(memberNum))
            payload["member_number"] = memberNum;
        if (SearchParams.TryGetValue("first_name", out var firstName) && !string.IsNullOrEmpty(firstName))
            payload["first_name"] = firstName;
        if (SearchParams.TryGetValue("last_name", out var lastName) && !string.IsNullOrEmpty(lastName))
            payload["last_name"] = lastName;
        if (SearchParams.TryGetValue("birthdate", out var birthdate) && !string.IsNullOrEmpty(birthdate))
            payload["birthdate"] = birthdate;
        if (!string.IsNullOrEmpty(Notes))
            payload["notes"] = Notes;
        if (Documents.Count > 0)
            payload["documents"] = Documents;

        return payload;
    }

    public Dictionary<string, object?> ToReplyPayload()
    {
        return new Dictionary<string, object?>
        {
            ["shared_identifier"] = SharedIdentifier,
            ["status"] = Status,
            ["matches"] = Matches
        };
    }

    /// <summary>
    /// Create from received request payload.
    /// </summary>
    public static SearchRequest FromRequest(Dictionary<string, object?> payload, string senderMipId, string senderOrg)
    {
        var searchParams = new Dictionary<string, string?>();

        if (payload.TryGetValue("member_number", out var memberNum) && memberNum != null)
            searchParams["member_number"] = memberNum.ToString();
        if (payload.TryGetValue("first_name", out var firstName) && firstName != null)
            searchParams["first_name"] = firstName.ToString();
        if (payload.TryGetValue("last_name", out var lastName) && lastName != null)
            searchParams["last_name"] = lastName.ToString();
        if (payload.TryGetValue("birthdate", out var birthdate) && birthdate != null)
            searchParams["birthdate"] = birthdate.ToString();

        return new SearchRequest
        {
            SharedIdentifier = payload.GetValueOrDefault("shared_identifier")?.ToString() ?? Guid.NewGuid().ToString(),
            Direction = "inbound",
            TargetMipIdentifier = senderMipId,
            TargetOrg = senderOrg,
            SearchParams = searchParams,
            Notes = payload.GetValueOrDefault("notes")?.ToString(),
            Documents = (payload.GetValueOrDefault("documents") as List<object>) ?? new List<object>()
        };
    }
}
