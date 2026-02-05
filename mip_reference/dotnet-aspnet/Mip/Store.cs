using Mip.Models;

namespace Mip;

/// <summary>
/// In-memory data store for MIP node data.
/// Uses a singleton pattern for this reference implementation.
/// </summary>
public class Store
{
    private static Store? _instance;
    private static readonly object _lock = new();

    public NodeIdentity? NodeIdentity { get; private set; }

    private readonly Dictionary<string, Connection> _connections = new();
    private readonly Dictionary<string, Member> _members = new();
    private readonly Dictionary<string, Endorsement> _endorsements = new();
    private readonly Dictionary<string, SearchRequest> _searchRequests = new();
    private readonly Dictionary<string, CogsRequest> _cogsRequests = new();
    private readonly List<ActivityEntry> _activityLog = new();

    public static Store Current
    {
        get
        {
            if (_instance == null)
            {
                lock (_lock)
                {
                    _instance ??= new Store();
                }
            }
            return _instance;
        }
    }

    public static void Reset()
    {
        lock (_lock)
        {
            _instance = new Store();
        }
    }

    public void SetNodeIdentity(NodeIdentity identity)
    {
        NodeIdentity = identity;
    }

    // Connection management
    public void AddConnection(Connection connection)
    {
        _connections[connection.MipIdentifier] = connection;
        LogActivity($"Connection added: {connection.OrganizationName} ({connection.Status})");
    }

    public Connection? FindConnection(string mipIdentifier)
    {
        return _connections.GetValueOrDefault(mipIdentifier);
    }

    public IEnumerable<Connection> ActiveConnections => _connections.Values.Where(c => c.IsActive);
    public IEnumerable<Connection> PendingConnections => _connections.Values.Where(c => c.IsPending);
    public IEnumerable<Connection> AllConnections => _connections.Values;

    // Member management
    public void AddMember(Member member)
    {
        _members[member.MemberNumber] = member;
    }

    public Member? FindMember(string memberNumber)
    {
        return _members.GetValueOrDefault(memberNumber);
    }

    public IEnumerable<Member> SearchMembers(Dictionary<string, string?> query)
    {
        return _members.Values.Where(m =>
        {
            if (query.TryGetValue("member_number", out var memberNum) && !string.IsNullOrEmpty(memberNum))
            {
                return m.MemberNumber.Contains(memberNum, StringComparison.OrdinalIgnoreCase);
            }

            if (query.TryGetValue("first_name", out var firstName) &&
                query.TryGetValue("last_name", out var lastName) &&
                !string.IsNullOrEmpty(firstName) && !string.IsNullOrEmpty(lastName))
            {
                var nameMatch = m.FirstName.Contains(firstName, StringComparison.OrdinalIgnoreCase) &&
                                m.LastName.Contains(lastName, StringComparison.OrdinalIgnoreCase);

                if (query.TryGetValue("birthdate", out var birthdate) && !string.IsNullOrEmpty(birthdate))
                {
                    return nameMatch && m.Birthdate == birthdate;
                }

                return nameMatch;
            }

            return false;
        });
    }

    public IEnumerable<Member> AllMembers => _members.Values;

    // Endorsement management
    public void AddEndorsement(Endorsement endorsement)
    {
        _endorsements[endorsement.Id] = endorsement;
        LogActivity($"Endorsement received from {endorsement.EndorserMipIdentifier}");
    }

    public IEnumerable<Endorsement> FindEndorsementsFor(string mipIdentifier)
    {
        return _endorsements.Values.Where(e => e.EndorsedMipIdentifier == mipIdentifier);
    }

    public IEnumerable<Endorsement> FindEndorsementsFrom(string mipIdentifier)
    {
        return _endorsements.Values.Where(e => e.EndorserMipIdentifier == mipIdentifier);
    }

    // Search request management
    public void AddSearchRequest(SearchRequest searchRequest)
    {
        _searchRequests[searchRequest.SharedIdentifier] = searchRequest;
        LogActivity($"Search request: {searchRequest.Direction} - {searchRequest.TargetOrg}");
    }

    public SearchRequest? FindSearchRequest(string sharedIdentifier)
    {
        return _searchRequests.GetValueOrDefault(sharedIdentifier);
    }

    public IEnumerable<SearchRequest> AllSearchRequests => _searchRequests.Values;

    // COGS request management
    public void AddCogsRequest(CogsRequest cogsRequest)
    {
        _cogsRequests[cogsRequest.SharedIdentifier] = cogsRequest;
        LogActivity($"COGS request: {cogsRequest.Direction} - {cogsRequest.TargetOrg}");
    }

    public CogsRequest? FindCogsRequest(string sharedIdentifier)
    {
        return _cogsRequests.GetValueOrDefault(sharedIdentifier);
    }

    public IEnumerable<CogsRequest> AllCogsRequests => _cogsRequests.Values;

    // Activity log
    public void LogActivity(string message)
    {
        _activityLog.Insert(0, new ActivityEntry
        {
            Timestamp = DateTime.UtcNow.ToString("o"),
            Message = message
        });

        // Keep only last 100 entries
        while (_activityLog.Count > 100)
        {
            _activityLog.RemoveAt(_activityLog.Count - 1);
        }
    }

    public IEnumerable<ActivityEntry> RecentActivity(int count = 20)
    {
        return _activityLog.Take(count);
    }
}

public class ActivityEntry
{
    public string Timestamp { get; set; } = "";
    public string Message { get; set; } = "";
}
