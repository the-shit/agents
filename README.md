# THE SHIT - Agents

[![Tests](https://img.shields.io/github/actions/workflow/status/the-shit/agents/tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/the-shit/agents/actions)
[![License](https://img.shields.io/github/license/the-shit/agents?style=flat-square)](LICENSE.md)

**Your GitHub workflows are manual. They shouldn't be.** Deploy specialized AI agent swarms that review PRs, triage issues, run tests, and ship code while you sleep.

Built on [Conduit UI](https://github.com/conduit-ui), powered by Claude, zero DevOps required.

## What This Does

Stop context-switching between coding and ops work. Deploy agent kits that handle the boring parts:

- **Architect** - Analyzes requirements, designs system architecture, creates technical specs
- **Implementer** - Writes production code, implements features, refactors, optimizes performance
- **Tester** - Writes test suites, runs automated tests, validates code quality and coverage
- **Reviewer** - Reviews PRs, enforces coding standards, identifies security issues, provides feedback

Each kit is a swarm of specialized agents that collaborate autonomously.

## The Problem

Your team is:
- Waiting hours for PR reviews
- Manually triaging duplicate issues
- Running the same test suites over and over
- Deploying by hand because CI/CD is "too complex"
- Writing boilerplate code instead of solving problems

You're paying developers to do robot work.

## Installation

```bash
# Download the binary
wget https://github.com/the-shit/agents/releases/latest/download/agents.phar

# Make it executable
chmod +x agents.phar
mv agents.phar /usr/local/bin/agents

# Or install globally
composer global require the-shit/agents
```

## Usage

### Deploy an Agent Kit (30 seconds)

```bash
# Deploy the PR reviewer agent swarm
agents deploy reviewer

# Deploy all kits at once
agents deploy architect
agents deploy implementer
agents deploy tester
agents deploy reviewer
```

### Run Agent Work

```bash
# Execute a specific agent kit
agents run reviewer

# Run with a specific task
agents run reviewer --task=review_pull_requests

# Run all pending tasks for a kit
agents run implementer
```

### Monitor Progress

```bash
# Watch agent activity in real-time
agents watch reviewer

# Output:
# ┌────────────────────┬─────────────────────────────┐
# │ Metric             │ Value                       │
# ├────────────────────┼─────────────────────────────┤
# │ Kit                │ reviewer                    │
# │ Status             │ running                     │
# │ Progress           │ 75%                         │
# │ Started            │ 2025-12-10 14:30:00         │
# │ Tasks Completed    │ 12                          │
# │ Tasks Pending      │ 4                           │
# └────────────────────┴─────────────────────────────┘
```

## Agent Kits

### Architect

System design and planning agent.

**Capabilities:**
- Design system architecture
- Define component structure
- Plan data flows
- Create technical specifications

**When to use:** Starting new features, refactoring large systems, technical planning

```bash
agents deploy architect
agents run architect --task=analyze_requirements
```

### Implementer

Code implementation agent.

**Capabilities:**
- Write production code
- Implement features from specs
- Refactor existing code
- Optimize performance

**When to use:** Feature development, code refactoring, performance optimization

```bash
agents deploy implementer
agents run implementer --task=implement_features
```

### Tester

Testing and QA agent.

**Capabilities:**
- Write unit and integration tests
- Run automated test suites
- Validate code quality
- Check test coverage

**When to use:** Ensuring code quality, CI/CD pipelines, pre-deployment validation

```bash
agents deploy tester
agents run tester --task=run_test_suites
```

### Reviewer

Code review and quality control agent.

**Capabilities:**
- Review pull requests automatically
- Enforce coding standards
- Identify security vulnerabilities
- Provide actionable feedback

**When to use:** PR automation, security audits, maintaining code quality

```bash
agents deploy reviewer
agents run reviewer --task=review_pull_requests
```

## Real-World Examples

### Auto-Review Every PR

```bash
# Deploy reviewer once
agents deploy reviewer

# Add to your CI/CD pipeline (.github/workflows/review.yml):
on: [pull_request]
jobs:
  review:
    runs-on: ubuntu-latest
    steps:
      - run: agents run reviewer --task=review_pull_requests
```

Now every PR gets:
- Code quality feedback
- Security vulnerability scans
- Style guide enforcement
- Actionable improvement suggestions

### Automated Testing Pipeline

```bash
# Pre-commit hook (.git/hooks/pre-commit):
#!/bin/bash
agents run tester --task=run_test_suites

if [ $? -ne 0 ]; then
    echo "Tests failed. Commit aborted."
    exit 1
fi
```

Tests run automatically. Broken code never reaches main.

### Feature Development Workflow

```bash
# 1. Design the feature
agents run architect --task=analyze_requirements

# 2. Implement it
agents run implementer --task=implement_features

# 3. Test it
agents run tester --task=write_unit_tests

# 4. Review it
agents run reviewer --task=check_code_quality

# Done. Ship it.
```

### Monitor All Agents

```bash
# Check status of all deployed kits
for kit in architect implementer tester reviewer; do
    echo "=== $kit ==="
    agents watch $kit
    echo ""
done
```

## Configuration

Agent kits are configured in `config/agents.php`:

```php
return [
    'kits' => [
        'architect' => [
            'description' => 'System design and architecture planning',
            'capabilities' => [
                'Design system architecture',
                'Define component structure',
                'Plan data flows',
                'Create technical specifications',
            ],
            'tasks' => [
                'analyze_requirements',
                'design_architecture',
                'define_interfaces',
                'create_diagrams',
            ],
        ],
        // ... other kits
    ],
];
```

Customize tasks, add new capabilities, or create entirely new agent kits.

## How It Works

Built on the [Conduit UI](https://github.com/conduit-ui) ecosystem:

- **[conduit-ui/connector](https://github.com/conduit-ui/connector)** - GitHub API client
- **[conduit-ui/commit](https://github.com/conduit-ui/commit)** - Commit analysis
- **[conduit-ui/action](https://github.com/conduit-ui/action)** - Actions automation
- **[conduit-ui/pr](https://github.com/conduit-ui/pr)** - PR management
- **[conduit-ui/issue](https://github.com/conduit-ui/issue)** - Issue tracking

Each agent kit uses these packages to interact with GitHub autonomously.

Powered by Claude for intelligent decision-making and code understanding.

## Who This Is For

- **Solo developers** - Get a full team of AI agents without hiring
- **Startups** - Ship faster without scaling DevOps headcount
- **Enterprise teams** - Automate repetitive workflows across dozens of repos

If you're manually doing work that could be automated, this is for you.

## FAQ

**Q: Does this replace my team?**
A: No. It replaces the boring parts (PR reviews, test running, issue triage) so your team can focus on hard problems.

**Q: What about code quality?**
A: Agent output follows your existing standards. It reads your style guides, linting configs, and existing patterns.

**Q: Is this secure?**
A: Agents have read/write access to repos you configure. Use GitHub tokens with appropriate scopes. Audit logs track all actions.

**Q: Can I customize agent behavior?**
A: Yes. Edit `config/agents.php` to add tasks, modify capabilities, or create new kits.

**Q: Does this work with private repos?**
A: Yes. Use a GitHub token with repo access.

## Requirements

- PHP 8.2+
- GitHub account with API token
- [Laravel Zero](https://laravel-zero.com) (bundled)

## Enterprise

Running agents across your organization? We provide:
- Custom agent kit development
- Multi-repo coordination
- Advanced workflow automation
- Dedicated support with SLA

Contact: [THE SHIT](https://github.com/the-shit)

## Part of THE SHIT

**Scaling Humans Into Tomorrow**

Other packages in the ecosystem:
- [the-shit/chat](https://github.com/the-shit/chat) - Multi-model chat application
- [the-shit/core](https://github.com/the-shit/core) - Core framework
- [the-shit/music](https://github.com/the-shit/music) - Spotify integration

## Development

```bash
# Clone the repo
git clone https://github.com/the-shit/agents.git
cd agents

# Install dependencies
composer install

# Run tests
./vendor/bin/pest

# Build binary
php agents app:build
```

## License

MIT License - see [LICENSE](LICENSE.md)

---

**Stop doing robot work. Deploy agent swarms.**

```bash
agents deploy reviewer
```
