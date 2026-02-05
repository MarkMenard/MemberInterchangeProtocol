using System.Text.Json.Serialization;

namespace Mip.Models;

/// <summary>
/// Represents a connection with another MIP node.
/// </summary>
public class Connection
{
    public static readonly string[] Statuses = { "PENDING", "ACTIVE", "DECLINED", "REVOKED" };

    public string MipIdentifier { get; set; } = "";
    public string MipUrl { get; set; } = "";
    public string? PublicKey { get; set; }
    public string OrganizationName { get; set; } = "";
    public string? ContactPerson { get; set; }
    public string? ContactPhone { get; set; }
    public string Status { get; set; } = "PENDING";
    public string Direction { get; set; } = ""; // "inbound" or "outbound"
    public bool ShareMyOrganization { get; set; } = true;
    public int DailyRateLimit { get; set; } = 100;
    public string CreatedAt { get; set; } = DateTime.UtcNow.ToString("o");
    public string? DeclineReason { get; set; }
    public string? RevokeReason { get; set; }

    [JsonIgnore]
    public bool IsActive => Status == "ACTIVE";
    [JsonIgnore]
    public bool IsPending => Status == "PENDING";
    [JsonIgnore]
    public bool IsDeclined => Status == "DECLINED";
    [JsonIgnore]
    public bool IsRevoked => Status == "REVOKED";
    [JsonIgnore]
    public bool IsInbound => Direction == "inbound";
    [JsonIgnore]
    public bool IsOutbound => Direction == "outbound";

    public string? PublicKeyFingerprint =>
        string.IsNullOrEmpty(PublicKey) ? null : Crypto.Fingerprint(PublicKey);

    public void Approve(Dictionary<string, object?>? nodeProfile = null, int dailyRateLimit = 100)
    {
        Status = "ACTIVE";
        DailyRateLimit = dailyRateLimit;
        if (nodeProfile != null)
            UpdateFromProfile(nodeProfile);
    }

    public void Decline(string? reason = null)
    {
        Status = "DECLINED";
        DeclineReason = reason;
    }

    public void Revoke(string? reason = null)
    {
        Status = "REVOKED";
        RevokeReason = reason;
    }

    public void Restore()
    {
        Status = "ACTIVE";
        RevokeReason = null;
    }

    public Dictionary<string, object?> ToNodeProfile()
    {
        return new Dictionary<string, object?>
        {
            ["mip_identifier"] = MipIdentifier,
            ["mip_url"] = MipUrl,
            ["organization_legal_name"] = OrganizationName,
            ["contact_person"] = ContactPerson,
            ["contact_phone"] = ContactPhone,
            ["public_key"] = PublicKey,
            ["share_my_organization"] = ShareMyOrganization
        };
    }

    /// <summary>
    /// Create from a connection request payload.
    /// </summary>
    public static Connection FromRequest(Dictionary<string, object?> payload, string direction = "inbound")
    {
        return new Connection
        {
            MipIdentifier = payload.GetValueOrDefault("mip_identifier")?.ToString() ?? "",
            MipUrl = payload.GetValueOrDefault("mip_url")?.ToString() ?? "",
            PublicKey = payload.GetValueOrDefault("public_key")?.ToString(),
            OrganizationName = payload.GetValueOrDefault("organization_legal_name")?.ToString() ?? "",
            ContactPerson = payload.GetValueOrDefault("contact_person")?.ToString(),
            ContactPhone = payload.GetValueOrDefault("contact_phone")?.ToString(),
            ShareMyOrganization = payload.GetValueOrDefault("share_my_organization") as bool? ?? true,
            Direction = direction,
            Status = "PENDING"
        };
    }

    private void UpdateFromProfile(Dictionary<string, object?> profile)
    {
        if (profile.TryGetValue("organization_legal_name", out var name) && name != null)
            OrganizationName = name.ToString()!;
        if (profile.TryGetValue("contact_person", out var person) && person != null)
            ContactPerson = person.ToString();
        if (profile.TryGetValue("contact_phone", out var phone) && phone != null)
            ContactPhone = phone.ToString();
        if (profile.TryGetValue("mip_url", out var url) && url != null)
            MipUrl = url.ToString()!;
        if (profile.TryGetValue("public_key", out var key) && key != null)
            PublicKey = key.ToString();
    }
}
