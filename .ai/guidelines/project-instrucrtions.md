## Project Workflow

For all implementation tasks, adhere to the following lifecycle:

1. **Planning Phase**: Before changing any code, create a structured plan in `docs/plans/` using the naming convention: `YYYY-MM-DD-TIME-short-task-name.md`.
2. **Plan Requirements**: Every plan must explicitly outline:
   - **Goal**: The ultimate objective of the task.
   - **Affected Items**: Files, modules, database tables, or infrastructure components.
   - **Implementation Steps**: A logical, step-by-step execution path.
   - **Verification/Testing Plan**: How changes will be validated.
   - **Risks & Assumptions**: Potential side effects or dependencies.
3. **Clarification**: Ask for clarification whenever ambiguity arises and dynamically update the plan.
4. **Verification**: After completing code changes, halt and ask the user to manually verify the results.
5. **Completion Reporting**: Upon receiving user approval, create a completion report in `docs/completed/` using the exact same filename as the plan.
6. **Report Requirements**: Every completion report must include:
   - **Summary**: What was successfully implemented.
   - **Changelog**: Exact files changed, added, or deleted.
   - **Validation**: Specific tests, checks, or manual verifications run.
   - **Postponed Items**: Tasks deferred to a later date.
   - **Follow-up Recommendations**: Next steps or technical debt to monitor.
   - **Update plans/idea.md**: After each phase completed, mark the completed phase as completed and add a completion summery below it. Add reference to the completed file too.
7. **Workflow Exceptions**: For small, low-risk changes, you may ask the user if planning/completion documents can be bypassed. **Never bypass documentation** if the change affects database migrations, models, core services, security, or other breaking areas.
8. **Version Control**: Only after completing a task (generating completion report), provide a clean, descriptive and detailed commit message summarizing what was added, changed, or removed.

---

## AI Agent & Sub-Agent Usage

- **Primary Agent Responsibility**: The main coding agent remains responsible for understanding the task, making final decisions, modifying files, and reporting results to the user.
- **Sub-Agent Exploration Allowed**: For medium or large implementation tasks, the agent may use sub-agents when helpful to explore the codebase faster or reduce risk.
- **When to Use Sub-Agents**: Use sub-agents when the task involves multiple independent areas, such as database schema, models, policies/permissions, services, Livewire components, routes, tests, or UI views.
- **Suggested Delegation Areas**: Sub-agents may be delegated to inspect:
  - Existing migrations, models, relationships, and database tables.
  - Routes, controllers, Livewire components, Blade views, Flux UI/admin screens, and public Tailwind routes.
  - Permissions, policies, gates, middleware, and security-sensitive flows.
  - Tests, factories, seeders, commands, jobs, queues, events, and scheduled tasks.
  - Existing project conventions and similar previously implemented features.
- **Delegation Boundaries**: Sub-agents must only explore, summarize, and recommend. They must not directly modify files, run destructive commands, change dependencies, execute migrations, or alter production data unless the user explicitly approves and the main agent coordinates the action.
- **User Approval Rule**: If the tool/session requires explicit user permission before launching sub-agents, ask the user first. If permission is already granted by the environment or project instructions, use sub-agents when they materially improve quality, speed, or safety.
- **Plan Integration**: Findings from sub-agents must be summarized in the planning document under **Affected Items**, **Risks & Assumptions**, or a dedicated **Discovery Notes** section before implementation begins.
- **Verification Responsibility**: The main agent must verify all sub-agent findings before applying changes. Do not blindly trust sub-agent conclusions.
- **Transparency**: In the final response or completion report, mention whether sub-agents were used and briefly summarize what they inspected.

---

## Laravel Coding Standards & Notes

- **Conventions**: Strictly follow standard Laravel structure, PSR-12 coding standards, and established project patterns before introducing new architectures.
- **Component Architecture**: Use class-based Livewire components only. Avoid single-file, inline, or anonymous components unless explicitly requested by the user.
- **Testing**: Proactively run relevant test suites and validation checks during local development.
- **Production Safety**: The project is actively in production. Exercise extreme caution; do not execute destructive migrations, raw database modifications, or unverified commands against production systems.

### Code Documentation & Commenting Rules
Apply strict PHPDoc documentation to all custom classes, methods, and properties using a explicit **What/Why/When** framework. 
* **What**: A concise description of what the class or method achieves.
* **Why**: The underlying business logic, intent, or architectural rationale for this specific implementation.
* **Ref/When**: The specific application context, event trigger, or lifecycle hook where this code executes.

*Note: Skip this detailed three-part comment blocks for native, unmodified Laravel framework boilerplate methods (such as default resource controllers or basic routing) unless they contain custom logic overrides.*
