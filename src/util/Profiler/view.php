<?php namespace mii\util; ?>

<style type="text/css">
    .mii {
        padding: 5px;
        overflow: auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
    }

    .mii table.profiler {
        width: 100%;
        max-width: 1920px;
        background-color: #fff;
        margin: 1px auto 0 auto;
        font-size: 13px;
        border-collapse: collapse;
    }

    .mii table.profiler th,
    .mii table.profiler td {
        padding: 0.2em 0.4em 0.15em 0.4em;
        background: #fff;
        border: 1px solid #bbb;
        border-width: 1px 0;
        text-align: left;
        font-weight: normal;
        font-size: 1em;
        color: #111;
        vertical-align: top;
        text-align: right;
    }

    .mii table.profiler th.name {
        text-align: left;
    }

    .mii table.profiler tr.group th {
        font-size: 1.25em;
        background: #333;
        color: #eee;
        border-color: #333;
    }

    .mii table.profiler tr.group td {
        background: #333;
        color: #777;
        border-color: #333;
    }

    .mii table.profiler tr.group td.time {
        padding-bottom: 0;
        padding-top: 0.3em;
    }

    .mii table.profiler tr.headers th {
        text-transform: lowercase;
        font-variant: small-caps;
        background: #ddd;
        color: #777;
    }

    .mii table.profiler tr.mark th.name {
        width: 40%;
        font-size: 1.2em;
        background: #fff;
        vertical-align: middle;
    }

    .mii table.profiler tr.mark td {
        padding: 0;
    }

    .mii table.profiler tr.mark.final td {
        padding: 0.2em 0.4em;
    }

    .mii table.profiler tr.mark td > div {
        position: relative;
        padding: 0.2em 0.4em;
    }

    .mii table.profiler tr.mark td div.value {
        position: relative;
        z-index: 2;
    }

    .mii table.profiler tr.mark td div.graph {
        position: absolute;
        top: 0;
        bottom: 0;
        right: 0;
        left: 100%;
        background: #71bdf0;
        z-index: 1;
    }

    .mii table.profiler tr.mark.memory td div.graph {
        background: #acd4f0;
    }

    .mii table.profiler tr.mark td.current {
        background: #eddecc;
    }

    .mii table.profiler tr.mark td.min {
        background: #daeed6;
    }

    .mii table.profiler tr.mark td.max {
        background: #e8d8d2;
    }

    .mii table.profiler tr.mark td.average {
        background: #ddd;
    }

    .mii table.profiler tr.mark td.total {
        background: #d0e3f0;
    }

    .mii table.profiler tr.time td {
        border-bottom: 0;
        font-weight: bold;
    }

    .mii table.profiler tr.memory td {
        border-top: 0;
    }

    .mii table.profiler tr.final th.name {
        background: #222;
        color: #fff;
    }

    .mii table.profiler abbr {
        border: 0;
        color: #777;
        font-weight: normal;
    }

    .mii table.profiler:hover tr.group td {
        color: #ccc;
    }

    .mii table.profiler:hover tr.mark td div.graph {
        background: #1197f0;
    }

    .mii table.profiler:hover tr.mark.memory td div.graph {
        background: #7cc1f0;
    }
</style>

<?php
$group_stats = Profiler::groupStats();
$group_cols = array('min', 'max', 'average', 'total');
$application_cols = array('min', 'max', 'average', 'current');
?>

<div class="mii">

    <table class="profiler">
        <?php $stats = Profiler::application(); ?>
        <tr class="final mark time">
            <th class="name" rowspan="2"
                scope="rowgroup"><?php echo 'Application Execution' . ' (' . $stats['count'] . ')' ?></th>
            <?php try {
                foreach ($application_cols as $key): ?>
                    <td class="<?php echo $key ?>">
                        <?php if ($stats[$key]['time'] > 2): ?>
                            <?php echo number_format($stats[$key]['time'], 3, ',', '') ?> <abbr title="seconds">s</abbr>
                        <?php else: ?>

                            <?php echo number_format($stats[$key]['time'], 5) * 1000 ?> <abbr title="seconds">ms</abbr>
                        <?php endif; ?>
                    </td>
                <?php endforeach;
            } catch (\Throwable $t) {
                throw  $t;
            } ?>
        </tr>
        <tr class="final mark memory">
            <?php foreach ($application_cols as $key): ?>
                <td class="<?php echo $key ?>"><?php echo number_format($stats[$key]['memory'] / 1024, 4) ?> <abbr
                            title="kilobyte">kB</abbr></td>
            <?php endforeach ?>
        </tr>
    </table>
    <?php foreach (Profiler::groups() as $group => $benchmarks): ?>
        <table class="profiler">
            <tr class="group">
                <th class="name"><?php echo ucfirst($group) ?></th>
                <td class="time"
                    colspan="4">
                    <?= number_format($group_stats[$group]['total']['memory'] / 1024, 3) ?>&thinsp;kB |
                    <span style="color:#bbb"><?= number_format($group_stats[$group]['total']['time'], 6) * 1000 ?></span>&thinsp;ms
                </td>
            </tr>
            <tr class="headers">
                <th class="name"><?php echo 'Benchmark' ?></th>
                <?php foreach ($group_cols as $key): ?>
                    <th class="<?php echo $key ?>"><?php echo ucfirst($key) ?></th>
                <?php endforeach ?>
            </tr>
            <?php foreach ($benchmarks as $name => $tokens): ?>
                <tr class="mark time">
                    <?php $stats = Profiler::stats($tokens) ?>
                    <th class="name" rowspan="2"
                        scope="rowgroup"><?php echo HTML::chars($name);
                        $count = \count($tokens);
                        if ($count > 1) echo " <sup>($count)</sup>" ?></th>
                    <?php foreach ($group_cols as $key):
                        $is_total = $key === 'total'; ?>
                        <td class="<?php echo $key ?>">
                            <div>
                                <div class="value"><?php echo number_format($stats[$key]['time'], 6) * 1000 ?><?php
                                    if ($is_total) echo "&thinsp;<abbr>ms</abbr>"
                                    ?>
                                </div>
                                <?php if ($is_total): ?>
                                    <div class="graph"
                                         style="left: <?php echo max(0, 100 - $stats[$key]['time'] / max(1, $group_stats[$group]['max']['time']) * 100) ?>%"></div>
                                <?php endif ?>
                            </div>
                        </td>
                    <?php endforeach ?>
                </tr>
                <tr class="mark memory">
                    <?php foreach ($group_cols as $key):
                        $is_total = $key === 'total'; ?>
                        <td class="<?php echo $key ?>">
                            <div>
                                <div class="value"><?php echo number_format($stats[$key]['memory'] / 1024, 4) ?><?php
                                    if ($is_total) echo "&thinsp;<abbr>kB</abbr>"
                                    ?></div>
                                <?php if ($is_total): ?>
                                    <div class="graph"
                                         style="left: <?php echo max(0, 100 - $stats[$key]['memory'] / max(1, $group_stats[$group]['max']['memory']) * 100) ?>%"></div>
                                <?php endif ?>
                            </div>
                        </td>
                    <?php endforeach ?>
                </tr>
            <?php endforeach ?>
        </table>
    <?php endforeach ?>

</div>
