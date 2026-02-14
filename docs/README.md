# SLO Cloud Documentation

Documentation is organized into two categories:

## Concepts (`concepts/`)

**Purpose**: Architectural decisions and system design

**Content**: High-level "what" and "why"
- System architecture and component purposes
- Design decisions and rationale
- Non-negotiable requirements
- Relationships between components
- Security model and constraints

**When to read**: Before making architectural decisions or implementing major features

**Files**:
- [ARCHITECTURE.md](concepts/ARCHITECTURE.md) - Overall system design
- [SCHEMA.md](concepts/SCHEMA.md) - Database schema and relationships
- [MVC.md](concepts/MVC.md) - MVC architecture pattern
- [PLUGIN.md](concepts/PLUGIN.md) - Plugin system architecture
- [AUTH.md](concepts/AUTH.md) - Authentication and authorization
- [SECURITY.md](concepts/SECURITY.md) - Security requirements
- [TESTING.md](concepts/TESTING.md) - Testing strategy
- [ACCESSIBILITY.md](concepts/ACCESSIBILITY.md) - Accessibility standards

## Implementation (`implementation/`)

**Purpose**: Step-by-step development patterns

**Content**: Detailed "how-to"
- Implementation workflows  
- Code patterns and examples
- Common operations and tasks
- Best practices
- Error handling strategies

**When to read**: During active development of features

**Files**:
- [MVC_GUIDE.md](implementation/MVC_GUIDE.md) - MVC implementation patterns
- [PLUGIN_GUIDE.md](implementation/PLUGIN_GUIDE.md) - Plugin development guide
- [DATA_CONNECTORS.md](implementation/DATA_CONNECTORS.md) - Connector implementation patterns

## Documentation Philosophy

**Concepts are language-agnostic** - Can be implemented in any language while preserving architectural intent

**Implementation guides are specific** - Provide concrete patterns for current technology stack (PHP 8.1+, MySQL 8.0+)

**Separation enables portability** - If reimplementing in different language, concepts remain valid while implementation details change
