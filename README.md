# Handsfree Licensing MT5 Backend

Backend para sistema de licenciamento MT5 usando PostgreSQL (Supabase).

## Requisitos

- PHP 8.1+
- PostgreSQL 12+
- Apache com mod_rewrite
- Composer

## Variáveis de Ambiente

```env
# Database
DB_HOST=your-supabase-host
DB_USER=your-supabase-user
DB_PASS=your-supabase-password
DB_NAME=your-database-name
DB_PORT=5432

# Security
WEBHOOK_SECRET=your-webhook-secret
MAX_SESSIONS=3
HEARTBEAT_TIMEOUT=300

# Email
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
FROM_EMAIL=noreply@handsfree.com