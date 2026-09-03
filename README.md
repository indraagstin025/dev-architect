# DEVArchitect 🏗️
### Universal AI ERD & Multi-Framework Database Schema Generator (Desktop App)

**DEVArchitect** is a modern developer-centric desktop application built with **Laravel 13** and **NativePHP**. It empowers software engineers to design, visualize, and generate production-ready database schemas and Entity Relationship Diagrams (ERD) using AI.

---

## 🌟 Key Features

- **Interactive ERD Canvas**: Real-time rendering of Entity Relationship Diagrams powered by Mermaid.js with pan, zoom, and export capabilities.
- **Multi-Framework & Multi-ORM Support**:
  - **Laravel**: Eloquent migration files (`.php`)
  - **Express.js / Node.js**: Prisma schema (`schema.prisma`) & Drizzle ORM (`schema.ts`)
  - **Java Spring Boot**: JPA / Hibernate entities (`.java`)
  - **Universal**: Raw SQL DDL (`schema.sql`)
- **Multi-Database Dialect Support**: Optimized types and constraints for **PostgreSQL**, **MySQL / MariaDB**, **SQLite**, and **SQL Server**.
- **AI-Powered Architecture Engine**: Integrated with **OpenRouter** (supporting powerful open-weight models like GPT OSS 120B, DeepSeek, and Llama) as well as OpenAI, Anthropic, and local Ollama.
- **Safe Dry-Run & Atomic File Injection**: Review, modify, and fine-tune schemas in-app before injecting them into local project directories with a single click and automatic rollback on failure.
- **Native OS Desktop Integration**: Built-in Windows Explorer folder picker, Windows desktop toast notifications, memory-state window sizing, system tray menu, and global hotkeys (`Ctrl+Alt+A`).

---

## 📚 Documentation

Detailed specifications and roadmap can be found in the [`docs/`](docs/) directory:
- [User Requirement Document (URD v2.0)](docs/URD_Laravel_Migration_AI_Generator.md)
- [Product Requirement Document (PRD v2.0)](docs/PRD_Laravel_Migration_AI_Generator.md)
- [Task List & Roadmap](docs/TASKS_Laravel_Migration_AI_Generator.md)

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+ with `pdo_pgsql` and `pdo_sqlite` extensions enabled
- Composer
- Node.js & NPM
- PostgreSQL database (e.g. via Laragon or Supabase)

### Installation
```bash
# Clone the repository
git clone git@github.com:indraagstin025/dev-architect.git
cd dev-architect/laravel-ai-generator

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Launch the desktop application
composer native:dev
```

---

## 📄 License
Open-sourced software licensed under the [MIT license](LICENSE).
