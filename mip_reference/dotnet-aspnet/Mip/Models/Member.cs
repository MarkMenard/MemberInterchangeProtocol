using System.Text.Json.Serialization;

namespace Mip.Models;

/// <summary>
/// Represents a member in the local organization.
/// </summary>
public class Member
{
    public string MemberNumber { get; set; } = "";
    public string? Prefix { get; set; }
    public string FirstName { get; set; } = "";
    public string? MiddleName { get; set; }
    public string LastName { get; set; } = "";
    public string? Suffix { get; set; }
    public string? Honorific { get; set; }
    public string? Rank { get; set; }
    public string? Birthdate { get; set; }
    public int? YearsInGoodStanding { get; set; }
    public string Status { get; set; } = "Active";
    public bool IsActive { get; set; } = true;
    public bool GoodStanding { get; set; } = true;
    public string? Email { get; set; }
    public string? Phone { get; set; }
    public string? Cell { get; set; }
    public Dictionary<string, string?> Address { get; set; } = new();
    public List<Dictionary<string, object?>> Affiliations { get; set; } = new();
    public List<Dictionary<string, object?>> LifeCycleEvents { get; set; } = new();

    [JsonIgnore]
    public string FullName
    {
        get
        {
            var parts = new[] { Prefix, FirstName, MiddleName, LastName, Suffix }
                .Where(p => !string.IsNullOrEmpty(p));
            return string.Join(" ", parts);
        }
    }

    [JsonIgnore]
    public string PartyShortName => $"{FirstName} {LastName?[..1]}";

    [JsonIgnore]
    public string MemberType
    {
        get
        {
            var activeAffiliation = Affiliations.FirstOrDefault(a =>
                a.TryGetValue("is_active", out var active) && active is bool b && b);
            return activeAffiliation?.GetValueOrDefault("member_type")?.ToString() ?? "Member";
        }
    }

    /// <summary>
    /// Format for search response.
    /// </summary>
    public Dictionary<string, object?> ToSearchResult()
    {
        return new Dictionary<string, object?>
        {
            ["member_number"] = MemberNumber,
            ["first_name"] = FirstName,
            ["last_name"] = LastName,
            ["birthdate"] = Birthdate,
            ["contact"] = new Dictionary<string, object?>
            {
                ["email"] = Email,
                ["phone"] = Phone,
                ["address"] = Address
            },
            ["group_status"] = new Dictionary<string, object?>
            {
                ["status"] = Status,
                ["is_active"] = IsActive,
                ["good_standing"] = GoodStanding
            },
            ["affiliations"] = Affiliations.Select(aff => new Dictionary<string, object?>
            {
                ["local_name"] = aff.GetValueOrDefault("local_name"),
                ["local_status"] = aff.GetValueOrDefault("local_status") ?? aff.GetValueOrDefault("status"),
                ["is_active"] = aff.GetValueOrDefault("is_active"),
                ["member_type"] = aff.GetValueOrDefault("member_type")
            }).ToList()
        };
    }

    /// <summary>
    /// Full member profile for COGS.
    /// </summary>
    public Dictionary<string, object?> ToMemberProfile()
    {
        return new Dictionary<string, object?>
        {
            ["member_number"] = MemberNumber,
            ["prefix"] = Prefix,
            ["first_name"] = FirstName,
            ["middle_name"] = MiddleName,
            ["last_name"] = LastName,
            ["suffix"] = Suffix,
            ["honorific"] = Honorific,
            ["rank"] = Rank,
            ["birthdate"] = Birthdate,
            ["years_in_good_standing"] = YearsInGoodStanding,
            ["group_status"] = new Dictionary<string, object?>
            {
                ["status"] = Status,
                ["is_active"] = IsActive
            },
            ["contact"] = new Dictionary<string, object?>
            {
                ["email"] = Email,
                ["phone"] = Phone,
                ["cell"] = Cell,
                ["address"] = Address
            },
            ["affiliations"] = Affiliations,
            ["life_cycle_events"] = LifeCycleEvents
        };
    }

    /// <summary>
    /// Quick status check response.
    /// </summary>
    public Dictionary<string, object?> ToStatusCheck()
    {
        return new Dictionary<string, object?>
        {
            ["member_number"] = MemberNumber,
            ["member_type"] = MemberType,
            ["party_short_name"] = PartyShortName,
            ["group_status"] = new Dictionary<string, object?>
            {
                ["status"] = Status,
                ["is_active"] = IsActive,
                ["good_standing"] = GoodStanding
            }
        };
    }

    /// <summary>
    /// Create from configuration.
    /// </summary>
    public static Member FromConfig(MemberConfig config)
    {
        return new Member
        {
            MemberNumber = config.MemberNumber,
            Prefix = config.Prefix,
            FirstName = config.FirstName,
            MiddleName = config.MiddleName,
            LastName = config.LastName,
            Suffix = config.Suffix,
            Honorific = config.Honorific,
            Rank = config.Rank,
            Birthdate = config.Birthdate,
            YearsInGoodStanding = config.YearsInGoodStanding,
            Status = config.Status,
            IsActive = config.IsActive,
            GoodStanding = config.GoodStanding,
            Email = config.Email,
            Phone = config.Phone,
            Cell = config.Cell,
            Address = config.Address == null ? new() : new Dictionary<string, string?>
            {
                ["address1"] = config.Address.Address1,
                ["address2"] = config.Address.Address2,
                ["city"] = config.Address.City,
                ["state"] = config.Address.State,
                ["postal_code"] = config.Address.PostalCode,
                ["country"] = config.Address.Country
            },
            Affiliations = config.Affiliations.Select(a => new Dictionary<string, object?>
            {
                ["local_name"] = a.LocalName,
                ["local_number"] = a.LocalNumber,
                ["local_location"] = a.LocalLocation,
                ["member_type"] = a.MemberType,
                ["local_status"] = a.LocalStatus,
                ["is_active"] = a.IsActive,
                ["level"] = a.Level,
                ["date"] = a.Date
            }).ToList(),
            LifeCycleEvents = config.LifeCycleEvents.Select(e => new Dictionary<string, object?>
            {
                ["event_name"] = e.EventName,
                ["date"] = e.Date,
                ["local_name"] = e.LocalName
            }).ToList()
        };
    }
}
