<?php
/**
 * Ruta: /app/Views/components/pagination.php
 * @var array $result  {page, last_page, total}
 * @var array $query   parámetros a conservar
 */

$page  = (int) ($result['page'] ?? 1);
$last  = (int) ($result['last_page'] ?? 1);
$query = $query ?? [];

if ($last <= 1) {
    return;
}

$link = static function (int $target) use ($query): string {
    $query['page'] = $target;

    return '?' . http_build_query(array_filter($query, static fn ($v): bool => $v !== null && $v !== ''));
};

$from = max(1, $page - 2);
$to   = min($last, $page + 2);
?>
<nav class="pagination" aria-label="Paginación">
    <a href="<?= e($link(max(1, $page - 1))) ?>" class="<?= $page <= 1 ? 'is-disabled' : '' ?>" rel="prev">‹</a>

    <?php if ($from > 1): ?>
        <a href="<?= e($link(1)) ?>">1</a>
        <?php if ($from > 2): ?><span class="is-disabled">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $from; $i <= $to; $i++): ?>
        <a href="<?= e($link($i)) ?>" class="<?= $i === $page ? 'is-active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($to < $last): ?>
        <?php if ($to < $last - 1): ?><span class="is-disabled">…</span><?php endif; ?>
        <a href="<?= e($link($last)) ?>"><?= $last ?></a>
    <?php endif; ?>

    <a href="<?= e($link(min($last, $page + 1))) ?>" class="<?= $page >= $last ? 'is-disabled' : '' ?>" rel="next">›</a>
</nav>

<p class="center small muted"><?= number_format((int) ($result['total'] ?? 0), 0, ',', '.') ?> registro(s)</p>
