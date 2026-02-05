namespace Mip;

/// <summary>
/// MIP request signature creation and verification.
/// Per spec: signature signs "timestamp + path + json_payload"
/// </summary>
public static class Signature
{
    /// <summary>
    /// Create a MIP request signature.
    /// </summary>
    public static string SignRequest(string privateKeyPem, string timestamp, string path, string? jsonBody = null)
    {
        var data = BuildSignatureData(timestamp, path, jsonBody);
        return Crypto.Sign(privateKeyPem, data);
    }

    /// <summary>
    /// Verify a MIP request signature.
    /// </summary>
    public static bool VerifyRequest(string publicKeyPem, string signature, string timestamp, string path, string? jsonBody = null)
    {
        var data = BuildSignatureData(timestamp, path, jsonBody);
        return Crypto.Verify(publicKeyPem, signature, data);
    }

    /// <summary>
    /// Check if timestamp is within acceptable window (±5 minutes).
    /// </summary>
    public static bool TimestampValid(string timestamp, int windowSeconds = 300)
    {
        if (!DateTime.TryParse(timestamp, out var requestTime))
            return false;

        var now = DateTime.UtcNow;
        var diff = Math.Abs((now - requestTime.ToUniversalTime()).TotalSeconds);
        return diff <= windowSeconds;
    }

    private static string BuildSignatureData(string timestamp, string path, string? jsonBody)
    {
        var data = timestamp + path;
        if (!string.IsNullOrEmpty(jsonBody))
            data += jsonBody;
        return data;
    }
}
