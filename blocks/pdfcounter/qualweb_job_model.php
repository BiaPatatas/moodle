<?php
// QualWeb evaluation job table for block_pdfcounter
// Table: block_pdfcounter_qualweb_jobs

/**
 * SQL for table creation (run in install.xml or via admin tool):
 *
 * <TABLE NAME="block_pdfcounter_qualweb_jobs" COMMENT="QualWeb evaluation jobs">
 *   <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
 *   <FIELD NAME="courseid" TYPE="int" LENGTH="10" NOTNULL="true"/>
 *   <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
 *   <FIELD NAME="status" TYPE="char" LENGTH="20" NOTNULL="true"/>
 *   <FIELD NAME="monitoring_id" TYPE="char" LENGTH="64" NOTNULL="false"/>
 *   <FIELD NAME="result_json" TYPE="text" NOTNULL="false"/>
 *   <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
 *   <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
 * </TABLE>
 */

/**
 * Example PHP structure for job management:
 */
class block_pdfcounter_qualweb_job {
    public $id;
    public $courseid;
    public $userid;
    public $status; // pending, running, completed, error
    public $monitoring_id;
    public $result_json;
    public $timecreated;
    public $timemodified;
}

/**
 * Usage:
 * - When a course page is loaded, check if a job exists for that course.
 * - If not, create a new job with status 'pending'.
 * - Cron job processes jobs with status 'pending' or 'running'.
 * - When completed, save result_json and set status to 'completed'.
 * - Block only displays result_json if status is 'completed', else shows status message.
 */
