<?php
/* For licensing terms, see /license.txt */

/**
 * Print a highlighted document inside a session
 *
 * @package chamilo.learnpath
 */

use Chamilo\CourseBundle\Entity\CDocument;

$_in_course = true;

require_once __DIR__.'/../inc/global.inc.php';

$current_course_tool = TOOL_LEARNPATH;

api_protect_course_script(true);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$lpId = isset($_GET['lp_id']) ? intval($_GET['lp_id']) : 0;
$courseInfo = api_get_course_info();
$courseCode = $courseInfo['code'];
$courseId = $courseInfo['real_id'];
$userId = api_get_user_id();
$sessionId = api_get_session_id();

$em = Database::getManager();
$documentRepo = $em->getRepository('ChamiloCourseBundle:CDocument');

// This page can only be shown from inside a learning path
if (!$id && !$lpId) {
    api_not_allowed(true);
    exit;
}

/** @var CDocument $document */
$document = $documentRepo->findOneBy(['cId' => $courseId, 'iid' => $id]);

if (empty($document)) {
    // Try with normal id
    /** @var CDocument $document */
    $document = $documentRepo->findOneBy(['cId' => $courseId, 'id' => $id]);

    if (empty($document)) {
        Display::return_message(get_lang('FileNotFound'), 'error');
        exit;
    }
}

$documentPathInfo = pathinfo($document->getPath());
$jplayer_supported_files = ['mp4', 'ogv', 'flv', 'm4v'];
$extension = isset($documentPathInfo['extension']) ? $documentPathInfo['extension'] : '';

$coursePath = api_get_path(SYS_COURSE_PATH).$courseInfo['directory'];
$documentPath = '/document'.$document->getPath();
$documentText = file_get_contents($coursePath.$documentPath);
$documentText = api_remove_tags_with_space($documentText);

$wordsInfo = preg_split('/ |\n/', $documentText, -1, PREG_SPLIT_OFFSET_CAPTURE);
$words = [];

foreach ($wordsInfo as $wordInfo) {
    $words[$wordInfo[1]] = nl2br($wordInfo[0]);
}

$htmlHeadXtra[] = '<script>
    var words = '.json_encode($words, JSON_OBJECT_AS_ARRAY).',
        wordsCount = '.count($words).'
</script>';
$htmlHeadXtra[] = api_get_js('highlighted_document/js/start.js');
$htmlHeadXtra[] = api_get_css(api_get_path(WEB_LIBRARY_JS_PATH).'highlighted_document/css/start.css');

$template = new Template(strip_tags($document->getTitle()));
$template->display_blank_template();
