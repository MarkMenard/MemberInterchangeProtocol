using System.Text.Json.Serialization;

namespace Mip.Models;

/// <summary>
/// Tracks Certificate of Good Standing requests (inbound and outbound).
/// </summary>
public class CogsRequest
{
    public static readonly string[] Statuses = { "PENDING", "APPROVED", "DECLINED" };

    public string SharedIdentifier { get; set; } = Guid.NewGuid().ToString();
    public string Direction { get; set; } = ""; // "inbound" or "outbound"
    public string TargetMipIdentifier { get; set; } = "";
    public string TargetOrg { get; set; } = "";
    public Dictionary<string, string?> RequestingMember { get; set; } = new();
    public string RequestedMemberNumber { get; set; } = "";
    public string? Notes { get; set; }
    public string Status { get; set; } = "PENDING";
    public Dictionary<string, object?>? Certificate { get; set; }
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

    public void Approve(Member member, Dictionary<string, object?> issuingOrg)
    {
        Status = "APPROVED";
        Certificate = new Dictionary<string, object?>
        {
            ["shared_identifier"] = SharedIdentifier,
            ["status"] = "APPROVED",
            ["good_standing"] = member.GoodStanding,
            ["issued_at"] = DateTime.UtcNow.ToString("o"),
            ["valid_until"] = DateTime.UtcNow.AddDays(90).ToString("o"),
            ["issuing_organization"] = issuingOrg,
            ["member_profile"] = member.ToMemberProfile()
        };
    }

    public void Decline(string? reason = null)
    {
        Status = "DECLINED";
        DeclineReason = reason;
        Certificate = new Dictionary<string, object?>
        {
            ["shared_identifier"] = SharedIdentifier,
            ["status"] = "DECLINED",
            ["good_standing"] = false,
            ["reason"] = reason
        };
    }

    public Dictionary<string, object?> ToRequestPayload()
    {
        var payload = new Dictionary<string, object?>
        {
            ["shared_identifier"] = SharedIdentifier,
            ["requesting_member"] = RequestingMember,
            ["requested_member_number"] = RequestedMemberNumber
        };

        if (!string.IsNullOrEmpty(Notes))
            payload["notes"] = Notes;

        return payload;
    }

    public Dictionary<string, object?> ToReplyPayload()
    {
        return Certificate ?? new Dictionary<string, object?>
        {
            ["shared_identifier"] = SharedIdentifier,
            ["status"] = Status
        };
    }

    /// <summary>
    /// Create from received request payload.
    /// </summary>
    public static CogsRequest FromRequest(Dictionary<string, object?> payload, string senderMipId, string senderOrg)
    {
        var requestingMember = new Dictionary<string, string?>();
        if (payload.TryGetValue("requesting_member", out var rm) && rm is Dictionary<string, object?> rmDict)
        {
            foreach (var kvp in rmDict)
            {
                requestingMember[kvp.Key] = kvp.Value?.ToString();
            }
        }

        return new CogsRequest
        {
            SharedIdentifier = payload.GetValueOrDefault("shared_identifier")?.ToString() ?? Guid.NewGuid().ToString(),
            Direction = "inbound",
            TargetMipIdentifier = senderMipId,
            TargetOrg = senderOrg,
            RequestingMember = requestingMember,
            RequestedMemberNumber = payload.GetValueOrDefault("requested_member_number")?.ToString() ?? "",
            Notes = payload.GetValueOrDefault("notes")?.ToString()
        };
    }
}
