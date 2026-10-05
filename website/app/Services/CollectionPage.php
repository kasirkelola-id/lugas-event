<?php

namespace App\Services;

final class CollectionPage
{
    public function __construct(public readonly int $page, public readonly int $limit) {}

    public static function fromRequest($request, int $default = 50): self
    {
        $values = [];
        foreach (['page' => 1, 'limit' => $default] as $key => $fallback) {
            $raw = $request->getGet($key) ?? $fallback;
            if ((!is_int($raw) && !is_string($raw)) || !preg_match('/^[1-9][0-9]{0,5}$/D', (string)$raw)) {
                throw new \InvalidArgumentException('Invalid pagination');
            }
            $values[$key] = (int)$raw;
        }
        if ($values['limit'] > 100 || $values['page'] > 10000) throw new \InvalidArgumentException('Invalid pagination');
        return new self($values['page'], $values['limit']);
    }

    /** Count only the already authorized, filtered collection; preserve its builder. */
    public function apply($builder, string $tieBreaker): array
    {
        $total = (clone $builder)->countAllResults();
        $builder->orderBy($tieBreaker, 'ASC')->limit($this->limit, ($this->page - 1) * $this->limit);
        return ['page' => $this->page, 'limit' => $this->limit, 'total' => $total,
            'total_pages' => (int)ceil($total / $this->limit), 'has_more' => $this->page * $this->limit < $total];
    }
}
