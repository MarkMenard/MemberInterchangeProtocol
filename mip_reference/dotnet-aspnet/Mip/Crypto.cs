using System.Security.Cryptography;
using System.Text;

namespace Mip;

/// <summary>
/// Cryptographic operations for MIP protocol.
/// Uses 2048-bit RSA keys with SHA256 signatures.
/// </summary>
public static class Crypto
{
    /// <summary>
    /// Generate a 2048-bit RSA key pair.
    /// Returns PEM-encoded private and public keys.
    /// </summary>
    public static (string PrivateKey, string PublicKey) GenerateKeyPair()
    {
        using var rsa = RSA.Create(2048);
        var privateKey = rsa.ExportRSAPrivateKeyPem();
        var publicKey = rsa.ExportRSAPublicKeyPem();
        return (privateKey, publicKey);
    }

    /// <summary>
    /// Calculate MD5 fingerprint of a public key (colon-separated hex).
    /// </summary>
    public static string Fingerprint(string publicKeyPem)
    {
        using var rsa = RSA.Create();
        rsa.ImportFromPem(publicKeyPem);
        var der = rsa.ExportRSAPublicKey();
        var hash = MD5.HashData(der);
        return string.Join(":", hash.Select(b => b.ToString("x2")));
    }

    /// <summary>
    /// Sign data with a private key using SHA256.
    /// Returns base64-encoded signature.
    /// </summary>
    public static string Sign(string privateKeyPem, string data)
    {
        using var rsa = RSA.Create();
        rsa.ImportFromPem(privateKeyPem);
        var dataBytes = Encoding.UTF8.GetBytes(data);
        var signature = rsa.SignData(dataBytes, HashAlgorithmName.SHA256, RSASignaturePadding.Pkcs1);
        return Convert.ToBase64String(signature);
    }

    /// <summary>
    /// Verify a signature using a public key.
    /// </summary>
    public static bool Verify(string publicKeyPem, string signatureBase64, string data)
    {
        try
        {
            using var rsa = RSA.Create();
            rsa.ImportFromPem(publicKeyPem);
            var signature = Convert.FromBase64String(signatureBase64);
            var dataBytes = Encoding.UTF8.GetBytes(data);
            return rsa.VerifyData(dataBytes, signature, HashAlgorithmName.SHA256, RSASignaturePadding.Pkcs1);
        }
        catch
        {
            return false;
        }
    }
}
