<?php
/**
 * ./modules/analytics/Module.php
 * Modul Analytics — tracking pencarian dan page views
 */

class AnalyticsModule
{
    /** @var array */
    private $config;

    /** @var Database */
    private $db;

    public function boot(array $config): void
    {
        $this->config = $config;
        $this->db     = Database::getInstance();
        $mgr = ModuleManager::getInstance();
        $mgr->addHook('search.performed', [$this, 'onSearch'], 10);
        $mgr->addHook('report.created',   [$this, 'onReportCreated'], 10);
    }

    public function onSearch(array $data): void
    {
        try {
            $this->db->query(
                "INSERT INTO analytics_search_daily (date,query_normalized,category,search_count,result_count,last_searched_at)
                 VALUES (CURDATE(),?,?,1,?,NOW())
                 ON DUPLICATE KEY UPDATE search_count=search_count+1,result_count=result_count+VALUES(result_count),last_searched_at=NOW()",
                [$data['normalized']??$data['query']??'',$data['category']??'all',$data['has_result']?1:0]
            );
        } catch (Exception $e) {}
    }

    public function onReportCreated(array $data): void
    {
        try {
            $this->db->query(
                "INSERT INTO analytics_reports_daily (date,category_id,report_count) VALUES (CURDATE(),?,1)
                 ON DUPLICATE KEY UPDATE report_count=report_count+1",
                [$data['category_id']??0]
            );
        } catch (Exception $e) {}
    }
}
