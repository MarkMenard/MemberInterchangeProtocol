namespace Mip.Models;

/// <summary>
/// Represents this node's identity in the MIP network.
/// </summary>
public class NodeIdentity
{
    public string MipIdentifier { get; set; } = "";
    public string PrivateKey { get; set; } = "";
    public string PublicKey { get; set; } = "";
    public string OrganizationName { get; set; } = "";
    public string? ContactPerson { get; set; }
    public string? ContactPhone { get; set; }
    public string MipUrl { get; set; } = "";
    public bool ShareMyOrganization { get; set; } = true;
    public int TrustThreshold { get; set; } = 1;
    public int Port { get; set; }

    public string PublicKeyFingerprint => Crypto.Fingerprint(PublicKey);

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
    /// Generate a new node identity from configuration.
    /// </summary>
    public static NodeIdentity FromConfig(NodeConfig config, int port)
    {
        var keys = Crypto.GenerateKeyPair();
        var mipId = Identifier.Generate(config.OrganizationName);

        return new NodeIdentity
        {
            MipIdentifier = mipId,
            PrivateKey = keys.PrivateKey,
            PublicKey = keys.PublicKey,
            OrganizationName = config.OrganizationName,
            ContactPerson = config.ContactPerson,
            ContactPhone = config.ContactPhone,
            MipUrl = $"http://localhost:{port}/mip/node/{mipId}",
            ShareMyOrganization = config.ShareMyOrganization,
            TrustThreshold = config.TrustThreshold,
            Port = port
        };
    }
}

/// <summary>
/// Configuration loaded from YAML file.
/// </summary>
public class NodeConfig
{
    public int Port { get; set; }
    public string OrganizationName { get; set; } = "";
    public string? ContactPerson { get; set; }
    public string? ContactPhone { get; set; }
    public bool ShareMyOrganization { get; set; } = true;
    public int TrustThreshold { get; set; } = 1;
    public List<MemberConfig> Members { get; set; } = new();
}

public class MemberConfig
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
    public AddressConfig? Address { get; set; }
    public List<AffiliationConfig> Affiliations { get; set; } = new();
    public List<LifeCycleEventConfig> LifeCycleEvents { get; set; } = new();
}

public class AddressConfig
{
    public string? Address1 { get; set; }
    public string? Address2 { get; set; }
    public string? City { get; set; }
    public string? State { get; set; }
    public string? PostalCode { get; set; }
    public string? Country { get; set; }
}

public class AffiliationConfig
{
    public string? LocalName { get; set; }
    public string? LocalNumber { get; set; }
    public string? LocalLocation { get; set; }
    public string? MemberType { get; set; }
    public string? LocalStatus { get; set; }
    public bool IsActive { get; set; } = true;
    public string? Level { get; set; }
    public string? Date { get; set; }
}

public class LifeCycleEventConfig
{
    public string? EventName { get; set; }
    public string? Date { get; set; }
    public string? LocalName { get; set; }
}
