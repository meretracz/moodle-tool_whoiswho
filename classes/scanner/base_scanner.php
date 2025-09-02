<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Base scanner abstract class for security scans
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl - Luuk Verhoeven
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\scanner;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base class for security scanners
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl - Luuk Verhoeven
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_scanner {

    /** @var array Scan results */
    protected array $results = [];

    /** @var array Scan statistics */
    protected array $stats = [
        'total_checked' => 0,
        'issues_found' => 0,
        'scan_time' => 0,
    ];

    /** @var int Start time for performance tracking */
    protected int $starttime;

    /** @var array Configuration options */
    protected array $config = [];

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config = []) {
        $this->config = array_merge($this->get_default_config(), $config);
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function get_default_config(): array {
        return [
            'verbose' => false,
            'limit' => 0,
        ];
    }

    /**
     * Execute the scan process
     *
     * @return array
     */
    public function execute(): array {
        $this->starttime = microtime(true);
        $this->results = [];
        $this->stats = [
            'total_checked' => 0,
            'issues_found' => 0,
            'scan_time' => 0,
        ];

        $this->before_scan();
        $this->perform_scan();
        $this->after_scan();

        $this->stats['scan_time'] = microtime(true) - $this->starttime;

        return $this->get_results();
    }

    /**
     * Perform the actual scan - must be implemented by child classes
     *
     * @return void
     */
    abstract protected function perform_scan(): void;

    /**
     * Get the scanner name
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Get the scanner description
     *
     * @return string
     */
    abstract public function get_description(): string;

    /**
     * Perform operations before starting the scan
     *
     * @return void
     */
    protected function before_scan(): void {
        mtrace("Starting scan: " . $this->get_name());
    }

    /**
     * Executes post-scan operations, such as logging scan completion details.
     *
     * @return void
     */
    protected function after_scan(): void {
        mtrace("Scan completed: " . $this->get_name());
        mtrace("Issues found: " . $this->stats['issues_found']);
    }

    /**
     * Add an issue to results
     *
     * @param string $type        Issue type
     * @param string $description Issue description
     * @param array $data         Additional data
     * @param string $severity    Severity level (critical, warning, info)
     * @return void
     */
    protected function add_issue(
        string $type,
        string $description,
        array $data = [],
        string $severity = 'warning'
    ): void {
        $this->results[] = [
            'type' => $type,
            'description' => $description,
            'severity' => $severity,
            'data' => $data,
            'timestamp' => time(),
        ];
        $this->stats['issues_found']++;
    }

    /**
     * Get scan results
     *
     * @return array
     */
    public function get_results(): array {
        return [
            'scanner' => $this->get_name(),
            'stats' => $this->stats,
            'issues' => $this->results,
            'timestamp' => time(),
        ];
    }

    /**
     * Store results in database
     *
     * @return void
     */
    public function store_results(): void {
        global $DB;

        // Store scan outcome as a compact run record for auditing.
        $now = time();
        $results = $this->get_results();

        $scan = (object) [
            'startedat' => $now,
            'finishedat' => $now,
            'status' => 'stored',
            'initiatedby' => null,
            'scopecontextid' => null,
            'meta' => json_encode([
                'scanner' => $this->get_name(),
                'stats' => $results['stats'] ?? [],
                'issues' => count($results['issues'] ?? []),
            ]),
        ];

        $DB->insert_record('tool_whoiswho_scan', $scan);
    }

}
