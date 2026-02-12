# Member Interchange Protocol
## What MIP Brings to the CGSNA Ecosystem

### Presentation to Executive Leadership

---

## Slide 1: Title

**Member Interchange Protocol**
*What MIP Brings to the CGSNA Ecosystem*

**Speaker Notes:**
- Introduce yourself and your role
- "The Certificates of Good Standing Committee is recommending that the Conference adopt MIP as the standard for inter-jurisdictional member data exchange. Today I want to walk you through what MIP delivers -- what it does, how it works, and where we are in bringing every vendor into the ecosystem."

---

## Slide 2: The Problem MIP Solves

**What Grand Lodges Need**

- Verify a brother's standing with his home jurisdiction
- Request Certificates of Good Standing across jurisdictional lines
- Search for member records when a brother visits or petitions for affiliation
- Keep certificates current when a member's standing changes

**Today, most of this is done by phone, email, and paper. It's slow, error-prone, and doesn't scale.**

**Speaker Notes:**
- Ground the conversation in the real, practical needs everyone in the room experiences
- "Every Grand Secretary in this room has fielded phone calls and emails asking about a brother's status. Every one of you has mailed or faxed a Certificate of Good Standing. MIP replaces those manual processes with a direct, secure connection between your system and theirs."

---

## Slide 3: What MIP Is

**The Member Interchange Protocol is:**

- An **open standard** -- published, free, available to anyone
- A **protocol, not a product** -- like the rules that let any email provider send messages to any other
- **Vendor-neutral** -- any software vendor can implement it
- **Direct** -- your system talks to another jurisdiction's system
- **Already in production** -- live and running today

**Speaker Notes:**
- "MIP is not a piece of software you buy. It's a set of rules for how member management systems talk to each other. Think of email: Gmail can email Yahoo can email Outlook -- because they all follow the same protocol. MIP does the same thing for member data."
- Spend time on the email analogy. This is the most important conceptual bridge for a non-technical audience.

---

## Slide 4: What You Can Do with MIP

**MIP 1.0 Capabilities:**

- **Search for members** across jurisdictions -- by member number or by name and date of birth
- **Check a member's standing** -- jurisdictions can opt into real-time status checks for use cases like verifying a visiting brother at the door of a lodge
- **Request and receive Certificates of Good Standing** electronically -- no more paper, phone calls, or fax
- **Keep certificates current** -- revoke a certificate when a member's standing changes, or renew an expired certificate through the protocol
- **Discover the network** -- see who's connected, find new jurisdictions through your existing connections

**Speaker Notes:**
- "MIP 1.0 covers everything this community has identified as essential. You can search for members. You can check standing. You can request and receive Certificates of Good Standing -- and keep them current when circumstances change. And you can discover other jurisdictions in the network through the connections you already have."

---

## Slide 5: You Control How Requests Are Handled

**Every jurisdiction decides for itself.**

- Incoming requests are queued for human review by default
- Your office reviews each request and decides what goes back
- Jurisdictions that want to can opt into automatic responses for specific functions
- Real-time status checks are available as an opt-in -- useful for mobile apps and digital Tyler use cases
- Nothing leaves your system without your jurisdiction's consent

**Speaker Notes:**
- "I want to be clear about something: MIP does not give anyone automatic access to your data. When a request comes in from another jurisdiction, your system receives it. You control what happens next. By default, requests are queued for someone in your office to review before anything goes out. If you want to allow automatic responses for certain things -- like real-time status checks at the door of a lodge -- you can opt into that. But it's your choice."

---

## Slide 6: Works With Every Vendor

**MIP is vendor-neutral by design.**

- Vendors implement MIP as a feature of the system your Grand Lodge already uses
- You don't change vendors. You don't change systems. Your vendor adds MIP capability.
- Open source reference implementations cover every major vendor's technology stack
- MIP is already running in production

| Implementation | Language | Relevant Vendors |
|---|---|---|
| Ruby / Sinatra | Ruby | Groupable, Grand View |
| .NET / ASP.NET Core | C# | Patriot Systems |
| PHP / Symfony | PHP | Amity |
| PHP / Laravel | PHP | |
| Node.js / Express | JavaScript | |

**Speaker Notes:**
- "You don't have to change vendors. You don't have to change systems. Your vendor adds MIP support to the system you already use. We've built open source reference implementations in five languages and frameworks -- covering the technology stack of every major vendor in the market. Any vendor can take one of these implementations and use it as a starting point."

---

## Slide 7: Security

**Proven cryptographic standards protect every transaction.**

- Every request is signed with RSA 2048-bit keys -- the same foundation used in online banking
- Signatures include a timestamp, the request path, and the payload -- preventing replay attacks, endpoint misuse, and tampering
- Member data travels between the two endpoints involved in the transaction
- Each jurisdiction controls who they connect to, what data they share, and how incoming requests are handled

**Speaker Notes:**
- "MIP uses the same cryptographic standards that protect online banking. Every request is digitally signed and verified. The signature includes a timestamp so it can't be replayed, it includes the specific endpoint being called so it can't be redirected, and it includes the data payload so it can't be tampered with. This is proven, well-understood security."

---

## Slide 8: The Network Grows Itself

**Web of trust makes onboarding easier over time.**

- First connections require manual approval -- you verify who you're connecting with
- When you connect with a jurisdiction, you exchange cryptographic endorsements
- Those endorsements can be presented to new jurisdictions as proof of identity
- The more connections you have, the easier it is to connect with the next one
- Each jurisdiction decides whether to accept endorsements or require manual approval for every connection

**Speaker Notes:**
- "The first few connections for any jurisdiction require manual approval -- and that's by design. You should verify who you're connecting with. But once you've established a few connections, those jurisdictions vouch for your identity cryptographically. When you reach out to a new jurisdiction, you can present those endorsements. If the new jurisdiction already trusts one of your endorsers, they can approve your connection automatically. The network gets easier to join the larger it gets."

---

## Slide 9: Built for the Future

**MIP 1.0 is the foundation. Future versions add capabilities without adding complexity.**

- The protocol is designed for backward-compatible growth
- Future capabilities under consideration:
  - **Member linking** -- formally connect a brother's records across jurisdictions
  - **Status change notifications** -- know when a linked member's standing changes
  - **Contact information updates** -- opt-in sharing of address changes between jurisdictions
- Every future feature is opt-in -- your jurisdiction decides what you participate in
- There is no cost to the Grand Lodge -- vendors bear the implementation cost as part of the system you already pay for

**Speaker Notes:**
- "MIP 1.0 covers the core needs. But the protocol is designed to grow. Future versions will let jurisdictions formally link member records, receive notifications when a linked member's status changes, and share contact information updates. Every one of these features will be opt-in. And the cost model stays the same -- vendors implement it as part of their product."

---

## Slide 10: Where We Are Now

**MIP is live and the ecosystem is being built out.**

- The protocol specification is being finalized based on real-world implementation feedback
- Shared tooling and libraries are being built so all vendors can adopt MIP with minimal effort
- A conformance validation system is in development to verify that every vendor's implementation communicates correctly with every other
- Everything is open source and available at **github.com/MarkMenard/MemberInterchangeProtocol**

**Speaker Notes:**
- "MIP is not a future plan -- it's running in production today. What we're doing now is building out the ecosystem. We're finalizing the spec based on what we've learned from real implementations. We're building shared libraries so vendors can adopt MIP quickly. And we're building a validation system so that when a vendor says they support MIP, we can verify that their implementation actually works correctly with every other vendor's implementation. That's how we guarantee interoperability."

---

## Slide 11: The Bottom Line

**MIP gives every Grand Lodge:**

- Secure, direct communication with any other jurisdiction
- Full control over how incoming requests are handled
- Interoperability with every vendor in the market
- A protocol that grows with your needs
- No additional cost -- vendors bear the implementation cost

**The Certificates of Good Standing Committee recommends the Conference adopt MIP. Implementations are live. The path forward is clear.**

**Speaker Notes:**
- "The COGS Committee is recommending adoption because MIP is ready. It's running in production. Every major vendor's technology stack is covered by open source reference implementations. The shared tooling and validation systems are being built right now. Any Grand Lodge, using any vendor, will be able to connect with any other Grand Lodge seamlessly. That's what MIP delivers."
- End with confidence. The recommendation is grounded in working software, not a proposal.
