using System.Text.Json;

namespace Mip.Models;

/// <summary>
/// Represents an endorsement in the web-of-trust.
/// </summary>
public class Endorsement
{
    public string Id { get; set; } = Guid.NewGuid().ToString();
    public string EndorserMipIdentifier { get; set; } = "";
    public string EndorsedMipIdentifier { get; set; } = "";
    public string EndorsedPublicKeyFingerprint { get; set; } = "";
    public string EndorsementDocument { get; set; } = "";
    public string EndorsementSignature { get; set; } = "";
    public string IssuedAt { get; set; } = "";
    public string ExpiresAt { get; set; } = "";

    public bool IsExpired
    {
        get
        {
            if (!DateTime.TryParse(ExpiresAt, out var expiry))
                return true;
            return expiry < DateTime.UtcNow;
        }
    }

    public bool ValidFor(string publicKeyFingerprint)
    {
        return !IsExpired && EndorsedPublicKeyFingerprint == publicKeyFingerprint;
    }

    /// <summary>
    /// Verify the endorsement signature using the endorser's public key.
    /// </summary>
    public bool VerifySignature(string endorserPublicKey)
    {
        if (IsExpired)
            return false;

        return Crypto.Verify(endorserPublicKey, EndorsementSignature, EndorsementDocument);
    }

    public Dictionary<string, object?> ToPayload()
    {
        return new Dictionary<string, object?>
        {
            ["endorser_mip_identifier"] = EndorserMipIdentifier,
            ["endorsed_mip_identifier"] = EndorsedMipIdentifier,
            ["endorsed_public_key_fingerprint"] = EndorsedPublicKeyFingerprint,
            ["endorsement_document"] = EndorsementDocument,
            ["endorsement_signature"] = EndorsementSignature,
            ["issued_at"] = IssuedAt,
            ["expires_at"] = ExpiresAt
        };
    }

    /// <summary>
    /// Create endorsement document and sign it.
    /// </summary>
    public static Endorsement Create(NodeIdentity endorserIdentity, string endorsedMipIdentifier, string endorsedPublicKey)
    {
        var fingerprint = Crypto.Fingerprint(endorsedPublicKey);
        var issuedAt = DateTime.UtcNow.ToString("o");
        var expiresAt = DateTime.UtcNow.AddYears(1).ToString("o");

        var document = JsonSerializer.Serialize(new Dictionary<string, object?>
        {
            ["type"] = "MIP_ENDORSEMENT_V1",
            ["endorser_mip_identifier"] = endorserIdentity.MipIdentifier,
            ["endorsed_mip_identifier"] = endorsedMipIdentifier,
            ["endorsed_public_key_fingerprint"] = fingerprint,
            ["issued_at"] = issuedAt,
            ["expires_at"] = expiresAt
        });

        var signature = Crypto.Sign(endorserIdentity.PrivateKey, document);

        return new Endorsement
        {
            EndorserMipIdentifier = endorserIdentity.MipIdentifier,
            EndorsedMipIdentifier = endorsedMipIdentifier,
            EndorsedPublicKeyFingerprint = fingerprint,
            EndorsementDocument = document,
            EndorsementSignature = signature,
            IssuedAt = issuedAt,
            ExpiresAt = expiresAt
        };
    }

    /// <summary>
    /// Create from received payload.
    /// </summary>
    public static Endorsement FromPayload(Dictionary<string, object?> payload)
    {
        return new Endorsement
        {
            EndorserMipIdentifier = payload.GetValueOrDefault("endorser_mip_identifier")?.ToString() ?? "",
            EndorsedMipIdentifier = payload.GetValueOrDefault("endorsed_mip_identifier")?.ToString() ?? "",
            EndorsedPublicKeyFingerprint = payload.GetValueOrDefault("endorsed_public_key_fingerprint")?.ToString() ?? "",
            EndorsementDocument = payload.GetValueOrDefault("endorsement_document")?.ToString() ?? "",
            EndorsementSignature = payload.GetValueOrDefault("endorsement_signature")?.ToString() ?? "",
            IssuedAt = payload.GetValueOrDefault("issued_at")?.ToString() ?? "",
            ExpiresAt = payload.GetValueOrDefault("expires_at")?.ToString() ?? ""
        };
    }
}
