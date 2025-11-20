<?php
namespace App\Core;

use PDO;

class QueryBuilder {
    protected string $table;
    protected array $select = ['*'];
    protected array $joins = [];
    protected array $where = [];
    protected array $params = [];
    protected ?string $groupBy = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $orderBy = [];

    public function __construct(string $table) {
        $this->table = $table;
    }

    public static function table(string $table): self {
        return new self($table);
    }

    public function select(array $columns): self {
        $this->select = $columns;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self {
        $this->joins[] = "INNER JOIN $table ON $first $operator $second";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self {
        $this->joins[] = "LEFT JOIN $table ON $first $operator $second";
        return $this;
    }

    public function where(string $column, string $operator, mixed $value = null): self {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->where[] = "$column $operator ?";
        $this->params[] = $value;
        return $this;
    }

    public function whereRaw(string $sql, array $params = []): self {
        $this->where[] = $sql;
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    public function groupBy(string $column): self {
        $this->groupBy = $column;
        return $this;
    }

    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self {
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    public function get(): array {
        $sql = $this->compileSelect();
        return Database::query($sql, $this->params)->fetchAll();
    }

    public function first(): ?array {
        $this->limit(1);
        $sql = $this->compileSelect();
        $result = Database::query($sql, $this->params)->fetch();
        return $result ?: null;
    }

    public function count(string $column = '*'): int {
        $originalSelect = $this->select;
        $this->select = ["COUNT($column) as count"];
        $sql = $this->compileSelect();
        $row = Database::query($sql, $this->params)->fetch();
        $this->select = $originalSelect; // restore
        return (int)($row['count'] ?? 0);
    }

    public function insert(array $data): int {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        Database::execute($sql, array_values($data));
        return (int)Database::lastInsertId();
    }

    public function insertIgnore(array $data): void {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        
        $sql = sprintf(
            'INSERT IGNORE INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        Database::execute($sql, array_values($data));
    }

    public function update(array $data): int {
        $set = [];
        $params = [];
        foreach ($data as $column => $value) {
            $set[] = "$column = ?";
            $params[] = $value;
        }
        
        // Append where params
        $params = array_merge($params, $this->params);

        $sql = sprintf(
            'UPDATE %s SET %s %s',
            $this->table,
            implode(', ', $set),
            $this->compileWhere()
        );

        return Database::execute($sql, $params);
    }

    public function delete(): int {
        $sql = sprintf('DELETE FROM %s %s', $this->table, $this->compileWhere());
        return Database::execute($sql, $this->params);
    }

    protected function compileSelect(): string {
        $sql = sprintf(
            'SELECT %s FROM %s%s%s%s%s%s',
            implode(', ', $this->select),
            $this->table,
            $this->compileJoins(),
            $this->compileWhere(),
            $this->compileGroupBy(),
            $this->compileOrderBy(),
            $this->compileLimit()
        );
        return $sql;
    }

    protected function compileJoins(): string {
        if (empty($this->joins)) {
            return '';
        }
        return ' ' . implode(' ', $this->joins);
    }

    protected function compileWhere(): string {
        if (empty($this->where)) {
            return '';
        }
        return ' WHERE ' . implode(' AND ', $this->where);
    }

    protected function compileGroupBy(): string {
        if ($this->groupBy === null) {
            return '';
        }
        return " GROUP BY {$this->groupBy}";
    }

    protected function compileOrderBy(): string {
        if (empty($this->orderBy)) {
            return '';
        }
        return ' ORDER BY ' . implode(', ', $this->orderBy);
    }

    protected function compileLimit(): string {
        if ($this->limit === null) {
            return '';
        }
        return " LIMIT {$this->limit}";
    }
}
