<?php

namespace App\Services\Parsers;

use App\Enums\DatabaseDialect;

class PrismaSchemaParser
{
    /**
     * @var array<string, mixed>
     */
    protected array $parsedData = [
        'datasource' => null,
        'generators' => [],
        'enums' => [],
        'models' => [],
        'relations' => [],
    ];

    public ?string $datasourceProvider = null;
    public ?DatabaseDialect $dialect = null;
    public array $models = [];
    public array $relations = [];
    public array $enums = [];

    /**
     * Parse Prisma schema content statically and return parser instance.
     *
     * @param string $content Raw schema.prisma file content
     * @return self
     */
    public static function parse(string $content): self
    {
        $parser = new self();
        $parser->parseSchema($content);
        return $parser;
    }

    /**
     * Parse Prisma schema content string into structured metadata using character-based scanning.
     *
     * @param string $content Raw schema.prisma file content
     * @return array<string, mixed>
     */
    public function parseSchema(string $content): array
    {
        $this->parsedData = [
            'datasource' => null,
            'generators' => [],
            'enums' => [],
            'models' => [],
            'relations' => [],
        ];

        // 1. Bersihkan komentar (// dan /* ... */) secara aman tanpa merusak string literal
        $cleanContent = $this->stripComments($content);

        // 2. Ekstrak blok-blok utama (datasource, generator, enum, model) menggunakan kedalaman brace
        $blocks = $this->extractBlocks($cleanContent);

        // 3. Proses blok datasource
        if (!empty($blocks['datasource'])) {
            $ds = $blocks['datasource'][0];
            $provider = null;
            $url = null;

            if (preg_match('/provider\s*=\s*["\']([^"\']+)["\']/', $ds['body'], $p)) {
                $provider = strtolower(trim($p[1]));
            }
            if (preg_match('/url\s*=\s*([^\r\n]+)/', $ds['body'], $u)) {
                $url = trim($u[1]);
            }

            $this->parsedData['datasource'] = [
                'name' => $ds['name'],
                'provider' => $provider,
                'url' => $url,
                'dialect' => $this->mapProviderToDialect($provider),
            ];
        }

        // 4. Proses blok enum
        foreach ($blocks['enum'] ?? [] as $enumBlock) {
            $values = array_values(array_filter(
                array_map('trim', preg_split('/\s+/', trim($enumBlock['body'])) ?: []),
                fn($v) => !empty($v)
            ));
            $this->parsedData['enums'][$enumBlock['name']] = $values;
        }

        // 5. Proses blok model
        foreach ($blocks['model'] ?? [] as $modelBlock) {
            $this->parseModelBlock($modelBlock['name'], $modelBlock['body']);
        }

        // 6. Hitung relasi (1:1, 1:N, N:N) dengan kardinalitas presisi
        $this->computeRelations();

        $this->datasourceProvider = $this->parsedData['datasource']['provider'] ?? null;
        $this->dialect = $this->parsedData['datasource']['dialect'] ?? null;
        $this->models = $this->parsedData['models'];
        $this->relations = $this->parsedData['relations'];
        $this->enums = $this->parsedData['enums'];

        return $this->parsedData;
    }

    /**
     * Hapus komentar baris (//) dan blok (/* ... *\/) tanpa mengganggu string kutipan.
     */
    protected function stripComments(string $content): string
    {
        $len = strlen($content);
        $result = '';
        $i = 0;

        while ($i < $len) {
            $char = $content[$i];

            // Masuk ke dalam string literal ("...")
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $result .= $char;
                $i++;
                while ($i < $len) {
                    $c = $content[$i];
                    $result .= $c;
                    if ($c === '\\' && $i + 1 < $len) {
                        $i++;
                        $result .= $content[$i];
                    } elseif ($c === $quote) {
                        break;
                    }
                    $i++;
                }
                $i++;
                continue;
            }

            // Komentar satu baris (//)
            if ($char === '/' && $i + 1 < $len && $content[$i + 1] === '/') {
                while ($i < $len && $content[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            // Komentar blok (/* ... */)
            if ($char === '/' && $i + 1 < $len && $content[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && !($content[$i] === '*' && $content[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2;
                continue;
            }

            $result .= $char;
            $i++;
        }

        return $result;
    }

    /**
     * Ekstrak blok tingkat atas berdasarkan pencocokan brace { }.
     *
     * @return array<string, list<array{name: string, body: string}>>
     */
    protected function extractBlocks(string $content): array
    {
        $blocks = [
            'datasource' => [],
            'generator' => [],
            'enum' => [],
            'model' => [],
        ];

        $len = strlen($content);
        $i = 0;

        while ($i < $len) {
            // Abaikan whitespace
            while ($i < $len && ctype_space($content[$i])) {
                $i++;
            }
            if ($i >= $len) break;

            // Baca keyword awal (datasource, generator, enum, model)
            $tokenStart = $i;
            while ($i < $len && (ctype_alnum($content[$i]) || $content[$i] === '_')) {
                $i++;
            }
            $keyword = substr($content, $tokenStart, $i - $tokenStart);

            if (!in_array($keyword, ['datasource', 'generator', 'enum', 'model'], true)) {
                // Lewati sampai baris berikutnya atau kurung buka
                while ($i < $len && $content[$i] !== "\n" && $content[$i] !== '{') {
                    $i++;
                }
                if ($i < $len && $content[$i] === '{') {
                    // Skip unknown block
                    $this->skipBlock($content, $i);
                }
                continue;
            }

            // Baca nama blok
            while ($i < $len && ctype_space($content[$i])) {
                $i++;
            }
            $nameStart = $i;
            while ($i < $len && (ctype_alnum($content[$i]) || $content[$i] === '_')) {
                $i++;
            }
            $name = substr($content, $nameStart, $i - $nameStart);

            // Cari kurung buka '{'
            while ($i < $len && $content[$i] !== '{') {
                $i++;
            }
            if ($i >= $len) break;

            // Lewati '{'
            $i++;
            $bodyStart = $i;
            $depth = 1;

            while ($i < $len && $depth > 0) {
                if ($content[$i] === '"' || $content[$i] === "'") {
                    $quote = $content[$i];
                    $i++;
                    while ($i < $len) {
                        if ($content[$i] === '\\' && $i + 1 < $len) {
                            $i += 2;
                            continue;
                        }
                        if ($content[$i] === $quote) {
                            $i++;
                            break;
                        }
                        $i++;
                    }
                    continue;
                }

                if ($content[$i] === '{') {
                    $depth++;
                } elseif ($content[$i] === '}') {
                    $depth--;
                }
                $i++;
            }

            $body = substr($content, $bodyStart, $i - $bodyStart - 1);
            $blocks[$keyword][] = [
                'name' => $name,
                'body' => trim($body),
            ];
        }

        return $blocks;
    }

    /**
     * Skip unknown block with balanced braces.
     */
    protected function skipBlock(string $content, int &$i): void
    {
        $len = strlen($content);
        $depth = 0;
        while ($i < $len) {
            if ($content[$i] === '{') $depth++;
            elseif ($content[$i] === '}') {
                $depth--;
                if ($depth <= 0) {
                    $i++;
                    return;
                }
            }
            $i++;
        }
    }

    /**
     * Parse isi blok model, menangani baris field bertingkat (multiline attributes) dan direktif model.
     */
    protected function parseModelBlock(string $modelName, string $body): void
    {
        // 1. Gabungkan baris yang memiliki tanda kurung atribut belum seimbang (multiline attributes)
        $logicalLines = $this->mergeMultilineAttributes($body);

        $fields = [];
        $modelDirectives = [];
        $compositePks = [];
        $compositeUniques = [];

        foreach ($logicalLines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Cek direktif model @@id, @@unique, @@index, @@map
            if (str_starts_with($line, '@@')) {
                $modelDirectives[] = $line;

                // Tangani @@id([field1, field2])
                if (preg_match('/@@id\s*\(\s*\[([^\]]+)\]\s*\)/', $line, $idMatch)) {
                    $compositePks = array_map('trim', explode(',', $idMatch[1]));
                }
                // Tangani @@unique([field1, field2])
                if (preg_match('/@@unique\s*\(\s*\[([^\]]+)\]\s*\)/', $line, $uMatch)) {
                    $compositeUniques[] = array_map('trim', explode(',', $uMatch[1]));
                }
                continue;
            }

            // Parsing field model:
            // Contoh: id Int @id @default(autoincrement())
            // Contoh: user User @relation("AuthorPosts", fields: [userId], references: [id])
            if (preg_match('/^(\w+)\s+([A-Za-z0-9_]+)(\[\]|\?)?(.*)$/s', $line, $fMatch)) {
                $fieldName = $fMatch[1];
                $rawType = $fMatch[2];
                $modifier = $fMatch[3] ?? '';
                $attributes = trim($fMatch[4] ?? '');

                $isList = ($modifier === '[]');
                $isOptional = ($modifier === '?');
                $isId = str_contains($attributes, '@id');
                $isUnique = str_contains($attributes, '@unique');
                $isUpdatedAt = str_contains($attributes, '@updatedAt');

                // Ekstrak @default(...) dengan balanced parenthesis
                $defaultValue = $this->extractBalancedDirective($attributes, '@default');

                // Ekstrak @relation(...)
                $relationInfo = $this->extractRelationDirective($attributes);

                $fields[$fieldName] = [
                    'name' => $fieldName,
                    'type' => $rawType,
                    'isList' => $isList,
                    'isOptional' => $isOptional,
                    'isId' => $isId,
                    'isUnique' => $isUnique,
                    'isUpdatedAt' => $isUpdatedAt,
                    'defaultValue' => $defaultValue,
                    'relation' => $relationInfo,
                    'isForeignKey' => false,
                    'isRelationField' => false,
                ];
            }
        }

        // Terapkan composite PKs jika ada
        foreach ($compositePks as $cpk) {
            if (isset($fields[$cpk])) {
                $fields[$cpk]['isId'] = true;
                $fields[$cpk]['isCompositeId'] = true;
            }
        }

        // Hitung daftar primary key (composite maupun single @id)
        $singlePks = [];
        foreach ($fields as $fName => $fData) {
            if ($fData['isId'] ?? false) {
                $singlePks[] = $fName;
            }
        }
        $primaryKey = !empty($compositePks) ? $compositePks : $singlePks;

        $this->parsedData['models'][$modelName] = [
            'name' => $modelName,
            'fields' => $fields,
            'directives' => $modelDirectives,
            'compositePks' => $compositePks,
            'compositeUniques' => $compositeUniques,
            'primaryKey' => $primaryKey,
            'uniqueKeys' => $compositeUniques,
        ];
    }

    /**
     * Gabungkan baris-baris bertingkat yang tanda kurungnya belum seimbang.
     *
     * @return list<string>
     */
    protected function mergeMultilineAttributes(string $body): array
    {
        $rawLines = preg_split('/\r?\n/', $body) ?: [];
        $merged = [];
        $buffer = '';
        $depth = 0;

        foreach ($rawLines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;

            $len = strlen($trimmed);
            for ($i = 0; $i < $len; $i++) {
                $c = $trimmed[$i];
                if ($c === '(' || $c === '[') $depth++;
                elseif ($c === ')' || $c === ']') $depth--;
            }

            if ($buffer === '') {
                $buffer = $trimmed;
            } else {
                $buffer .= ' ' . $trimmed;
            }

            if ($depth <= 0) {
                $merged[] = $buffer;
                $buffer = '';
                $depth = 0;
            }
        }

        if ($buffer !== '') {
            $merged[] = $buffer;
        }

        return $merged;
    }

    /**
     * Ekstrak isi direktif dengan pencocokan tanda kurung seimbang.
     */
    protected function extractBalancedDirective(string $attributes, string $directive): ?string
    {
        $needle = $directive . '(';
        $pos = strpos($attributes, $needle);
        if ($pos === false) {
            return null;
        }

        $start = $pos + strlen($needle);
        $len = strlen($attributes);
        $depth = 1;
        $end = $start;

        while ($end < $len && $depth > 0) {
            if ($attributes[$end] === '(') $depth++;
            elseif ($attributes[$end] === ')') $depth--;
            $end++;
        }

        if ($depth === 0) {
            return trim(substr($attributes, $start, $end - $start - 1));
        }

        return null;
    }

    /**
     * Ekstrak detail direktif @relation("RelationName", fields: [...], references: [...])
     *
     * @return array{name: ?string, fields: list<string>, references: list<string>}|null
     */
    protected function extractRelationDirective(string $attributes): ?array
    {
        $body = $this->extractBalancedDirective($attributes, '@relation');
        if ($body === null) {
            return null;
        }

        $relName = null;
        $fieldsArray = [];
        $refsArray = [];

        // Cek nama relasi di posisi argumen pertama: @relation("Name", ...) atau name: "Name"
        if (preg_match('/^(?:name\s*:\s*)?["\']([^"\']+)["\']/', $body, $nm)) {
            $relName = $nm[1];
        }

        if (preg_match('/fields\s*:\s*\[([^\]]*)\]/', $body, $fld)) {
            $fieldsArray = array_values(array_filter(array_map('trim', explode(',', $fld[1]))));
        }

        if (preg_match('/references\s*:\s*\[([^\]]*)\]/', $body, $ref)) {
            $refsArray = array_values(array_filter(array_map('trim', explode(',', $ref[1]))));
        }

        return [
            'name' => $relName,
            'fields' => $fieldsArray,
            'references' => $refsArray,
        ];
    }

    /**
     * Hitung relasi antar model secara presisi:
     * - 1:1 jika foreign key bertipe @unique
     * - 1:N jika foreign key tidak unik
     * - N:N jika kedua sisi relasi bertipe list []
     */
    protected function computeRelations(): void
    {
        $models = &$this->parsedData['models'];
        $modelNames = array_keys($models);

        foreach ($models as $sourceName => &$model) {
            foreach ($model['fields'] as $fieldName => &$field) {
                $targetModel = $field['type'];

                if (!in_array($targetModel, $modelNames, true)) {
                    continue;
                }

                $field['isRelationField'] = true;
                $relDirective = $field['relation'] ?? null;
                $relName = $relDirective['name'] ?? null;

                // KASUS 1: Owning side yang mendeklarasikan @relation(fields: [...], references: [...])
                if (!empty($relDirective['fields'])) {
                    $fkField = $relDirective['fields'][0] ?? null;
                    $isFkUnique = false;

                    if ($fkField && isset($model['fields'][$fkField])) {
                        $model['fields'][$fkField]['isForeignKey'] = true;
                        $model['fields'][$fkField]['referencesModel'] = $targetModel;
                        $isFkUnique = !empty($model['fields'][$fkField]['isUnique']);
                    }

                    // Tentukan kardinalitas: 1:1 jika FK unik, 1:N jika biasa
                    $cardinality = $isFkUnique ? 'one-to-one' : 'one-to-many';

                    $this->parsedData['relations'][] = [
                        'from' => $sourceName,
                        'to' => $targetModel,
                        'type' => $cardinality,
                        'relationName' => $relName,
                        'field' => $fieldName,
                        'foreignKey' => $fkField,
                        'isOwning' => true,
                    ];
                } elseif ($field['isList']) {
                    // Cek apakah target model juga memiliki field list balik ke source model (Implisit Many-to-Many N:N)
                    $targetFields = $models[$targetModel]['fields'] ?? [];
                    $isManyToMany = false;

                    foreach ($targetFields as $tf) {
                        if ($tf['type'] === $sourceName && $tf['isList']) {
                            $isManyToMany = true;
                            break;
                        }
                    }

                    if ($isManyToMany) {
                        // Catat N:N jika belum dicatat dari sisi sebaliknya
                        $alreadyExists = false;
                        foreach ($this->parsedData['relations'] as $r) {
                            if ($r['type'] === 'many-to-many' &&
                                (($r['from'] === $sourceName && $r['to'] === $targetModel) ||
                                 ($r['from'] === $targetModel && $r['to'] === $sourceName))) {
                                $alreadyExists = true;
                                break;
                            }
                        }

                        if (!$alreadyExists) {
                            $this->parsedData['relations'][] = [
                                'from' => $sourceName,
                                'to' => $targetModel,
                                'type' => 'many-to-many',
                                'relationName' => $relName,
                                'field' => $fieldName,
                                'foreignKey' => null,
                                'isOwning' => false,
                            ];
                        }
                    } else {
                        // Reverse virtual list field untuk 1:N
                        $this->parsedData['relations'][] = [
                            'from' => $sourceName,
                            'to' => $targetModel,
                            'type' => 'virtual-reverse',
                            'relationName' => $relName,
                            'field' => $fieldName,
                            'foreignKey' => null,
                            'isOwning' => false,
                        ];
                    }
                }
            }
        }
    }

    /**
     * Hasilkan diagram Mermaid ERD yang akurat dengan kardinalitas presisi:
     * - 1:1  -> EntityA ||--|| EntityB : "label"
     * - 1:N  -> EntityA ||--o{ EntityB : "label"
     * - N:N  -> EntityA }o--o{ EntityB : "label"
     */
    public function toMermaid(): string
    {
        if (empty($this->parsedData['models'])) {
            return "erDiagram\n";
        }

        $lines = ["erDiagram"];
        $models = $this->parsedData['models'];
        $renderedRelations = [];

        // 1. Render relasi antar entitas
        foreach ($this->parsedData['relations'] as $rel) {
            // Abaikan virtual-reverse (relasi balik tanpa FK) agar tidak tergambar dua kali
            if ($rel['type'] === 'virtual-reverse') {
                continue;
            }

            $from = $rel['from'];
            $to = $rel['to'];
            $label = $rel['relationName'] ?? $rel['field'] ?? 'rel';
            $relKey = "{$from}-{$to}-{$label}";

            if (isset($renderedRelations[$relKey])) {
                continue;
            }

            if ($rel['type'] === 'one-to-one') {
                // 1:1 -> Parent ||--|| Child
                $lines[] = "    {$to} ||--|| {$from} : \"{$label}\"";
            } elseif ($rel['type'] === 'many-to-many') {
                // N:N -> EntityA }o--o{ EntityB
                $lines[] = "    {$from} }o--o{ {$to} : \"{$label}\"";
            } else {
                // 1:N -> Parent ||--o{ Child
                $lines[] = "    {$to} ||--o{ {$from} : \"{$label}\"";
            }

            $renderedRelations[$relKey] = true;
        }

        // 2. Render struktur tabel dan kolom entitas
        foreach ($models as $modelName => $model) {
            $lines[] = "    {$modelName} {";

            foreach ($model['fields'] as $field) {
                // Lewati virtual relation field (e.g. posts Post[])
                if ($field['isRelationField']) {
                    continue;
                }

                $type = $this->normalizeMermaidType($field['type']);
                $name = $field['name'];
                $meta = '';

                if ($field['isId']) {
                    $meta = 'PK';
                } elseif (!empty($field['isForeignKey'])) {
                    $meta = 'FK';
                } elseif ($field['isUnique']) {
                    $meta = 'UK';
                }

                $lines[] = "        {$type} {$name}" . ($meta ? " {$meta}" : '');
            }

            $lines[] = "    }";
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Normalisasi tipe data skalar Prisma ke format Mermaid yang bersih.
     */
    protected function normalizeMermaidType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'bigint' => 'int',
            'string' => 'string',
            'boolean' => 'boolean',
            'datetime' => 'datetime',
            'float', 'decimal' => 'float',
            'json' => 'json',
            'bytes' => 'binary',
            default => strtolower($type),
        };
    }

    /**
     * Map Prisma provider string to DEVArchitect DatabaseDialect (nullable if unknown).
     */
    protected function mapProviderToDialect(?string $provider): ?DatabaseDialect
    {
        return match (strtolower((string)$provider)) {
            'postgresql', 'postgres' => DatabaseDialect::POSTGRESQL,
            'mysql' => DatabaseDialect::MYSQL,
            'sqlite' => DatabaseDialect::SQLITE,
            'sqlserver', 'sqlsrv' => DatabaseDialect::SQLSERVER,
            default => null,
        };
    }
}
