<?php
if ( ! defined('ABSPATH') ) { exit; }

final class FrmMidigatorShortcodeHelper {

    public function currentUrlWithout(array $removeKeys): string {

        $scheme = is_ssl() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $uri    = $_SERVER['REQUEST_URI'] ?? '';
        $url    = $scheme . '://' . $host . $uri;

        $parts = wp_parse_url($url);
        $path  = $parts['path'] ?? '';
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($removeKeys as $k) {
            unset($query[$k]);
        }

        $base = $scheme . '://' . $host . $path;

        if (!empty($query)) {
            $base .= '?' . http_build_query($query);
        }

        return $base;

    }

    public function addQueryArgSafe(string $url, string $key, string $value): string {
        return add_query_arg([$key => $value], $url);
    }

    public function formatDateTime($raw, string $format = 'Y-m-d H:i'): string {

        $raw = (string) $raw;
        if ($raw === '') return '';

        $ts = strtotime($raw);
        if (!$ts) return '';

        return date($format, $ts);
        
    }

    public function renderPagination(array $args = []): string {

        $currentPage  = isset($args['current_page']) ? max(1, (int) $args['current_page']) : 1;
        $totalPages   = isset($args['total_pages']) ? max(1, (int) $args['total_pages']) : 1;
        $baseUrl      = isset($args['base_url']) ? (string) $args['base_url'] : '';
        $pageParam    = isset($args['page_param']) ? (string) $args['page_param'] : 'page';
        $wrapperClass = isset($args['wrapper_class']) ? (string) $args['wrapper_class'] : 'mid-pre-footer';
        $pagerClass   = isset($args['pager_class']) ? (string) $args['pager_class'] : 'mid-pre-pager';
        $btnClass     = isset($args['btn_class']) ? (string) $args['btn_class'] : 'mid-pre-btn';
        $pageClass    = isset($args['page_class']) ? (string) $args['page_class'] : 'mid-pre-page';

        $prevDisabled = ($currentPage <= 1);
        $nextDisabled = ($currentPage >= $totalPages);

        $prevUrl = $this->addQueryArgSafe($baseUrl, $pageParam, (string) max(1, $currentPage - 1));
        $nextUrl = $this->addQueryArgSafe($baseUrl, $pageParam, (string) min($totalPages, $currentPage + 1));

        ob_start();
        ?>
        <div class="<?php echo esc_attr($wrapperClass); ?>">
            <div class="<?php echo esc_attr($pagerClass); ?>">
                <a class="<?php echo esc_attr($btnClass . ($prevDisabled ? ' is-disabled' : '')); ?>"
                   href="<?php echo $prevDisabled ? '#' : esc_url($prevUrl); ?>"
                   <?php echo $prevDisabled ? 'aria-disabled="true"' : ''; ?>
                >Prev</a>

                <span class="<?php echo esc_attr($pageClass); ?>">
                    <?php echo esc_html("Page {$currentPage} / {$totalPages}"); ?>
                </span>

                <a class="<?php echo esc_attr($btnClass . ($nextDisabled ? ' is-disabled' : '')); ?>"
                   href="<?php echo $nextDisabled ? '#' : esc_url($nextUrl); ?>"
                   <?php echo $nextDisabled ? 'aria-disabled="true"' : ''; ?>
                >Next</a>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();

    }

    public function deletePreventionById(int $id): bool {

        $preventionHelper = new FrmMidigatorPreventionHelper();
        return $preventionHelper->deletePreventionById($id);

    }

    public function deletePreventions( array $preventionIds ): bool {

        $preventionHelper = new FrmMidigatorPreventionHelper();
        foreach ($preventionIds as $id) {
            $preventionHelper->deletePreventionById($id);
        }

        return true;

    }    

    public function deletePreventionsAll( array $filter=[] ): bool {

        // Possible for now: is_resolved=true/false

        $preventionHelper = new FrmMidigatorPreventionHelper();
        $preventionHelper->deletePreventionsAll( $filter );

        return true;

    }

}