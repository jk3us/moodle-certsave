<?php

require_once("$CFG->libdir/externallib.php");
class local_c4k_external extends external_api {
	function my_certificates_parameters() {
		return new external_function_parameters(
			array('user_id' => new external_value(PARAM_INT, 'id of user in moodle'))
		);
	}

	function my_certificates_returns() {
		return new external_multiple_structure(
			new external_single_structure(
				array(
					'id' => new external_value(PARAM_INT, 'certificate id'),
					'course_id' => new external_value(PARAM_INT, 'course id'),
					'mod_id' => new external_value(PARAM_INT, 'course module id'),
					'title' => new external_value(PARAM_TEXT, 'certificate name'),
				)
			)
		);
	}

	public static function my_certificates($user_id) {
		global $CFG, $DB;
		$params = self::validate_parameters(self::my_certificates_parameters(), array('user_id'=>$user_id));
		$module = $DB->get_record('modules', array('name' => 'certificate'));
		$certs = array();

		// Get my classes
		$courses = $DB->get_records_sql("SELECT c.id
			FROM {course} c
			INNER JOIN {context} cx ON c.id = cx.instanceid
			AND cx.contextlevel = '50'
			INNER JOIN {role_assignments} ra ON cx.id = ra.contextid
			INNER JOIN {role} r ON ra.roleid = r.id
			INNER JOIN {user} usr ON ra.userid = usr.id
			WHERE r.name = 'Student' AND usr.id=?", array($params['user_id']));
		foreach ($courses as $course) {
			$certmods = $DB->get_records("course_modules", array('course'=>$course->id, 'module'=>$module->id));
			$modinfo = get_fast_modinfo($course, $params['user_id']);
			foreach ($certmods as $mod) {
				$certificate = $DB->get_record("certificate", array('id'=>$mod->instance));
				$cm = $modinfo->get_cm($mod->id);
				$context = get_context_instance(CONTEXT_MODULE, $cm->id);
				$ci = new condition_info($cm);
				$available = $ci->is_available($_info, false, $student->id);
				if ($available) {
					$cert = array();
					$cert['id'] = $certificate->id;
					$cert['course_id'] = $course->id;
					$cert['mod_id'] = $cm->id;
					$cert['title'] = $certificate->name;
					$certs[] = $cert;
				}
			}
		}

		//$group = array();
		//$group['id']=$params['user_id'];
		//$group['title']='testing';
		//$certs[] = $group;
		return $certs;
	}

}
