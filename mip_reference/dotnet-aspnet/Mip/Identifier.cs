using System.Security.Cryptography;
using System.Text;

namespace Mip;

/// <summary>
/// Generates MIP identifiers per the spec:
/// MD5(UUID + organization_name)
/// </summary>
public static class Identifier
{
    public static string Generate(string organizationName)
    {
        var uuid = Guid.NewGuid().ToString();
        var input = uuid + organizationName;
        var hashBytes = MD5.HashData(Encoding.UTF8.GetBytes(input));
        return Convert.ToHexString(hashBytes).ToLowerInvariant();
    }
}
