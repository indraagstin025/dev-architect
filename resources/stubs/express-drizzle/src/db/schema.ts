// Struktur awal Drizzle (SQLite) — skema AI dari DEVArchitect akan ditambahkan ke berkas ini.
import { integer, sqliteTable, text } from 'drizzle-orm/sqlite-core';

export const health = sqliteTable('health', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  status: text('status').notNull().default('ok'),
});
