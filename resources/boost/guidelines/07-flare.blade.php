@if ($assist->hasMcpEnabled() && (new \MikeBronner\DevelopmentSettings\Support\FlareMcpServer)->isWantedIn(base_path()))
# Flare

This project requires a Flare client, and laravel-development-settings adds Flare's MCP server, named `flare`, to every agent Laravel Boost configures MCP for. Flare collects the project's errors, logs and performance data.

- Use the `flare` MCP server when you investigate a production error, an exception, a log entry or a slow request. Read the occurrences, the stack trace and the context there before you change code.
- Each repository reports to its own Flare project, and this guideline names none. When you do not know which project this repository reports to, list the projects through the `flare` server and choose the one that matches this repository. When more than one matches, or none does, ask the user.
- The `flare` server signs in with OAuth. When a call fails because the server is not authorized, ask the user to authorize it in their agent, for example with `/mcp` in Claude Code. Never ask for a Flare API token, and never write one into a file.
@endif
