<?php

function local_certsave_cron() {
		global $DB,$CFG;
		require_once($CFG->libdir.'/conditionlib.php');
		require_once("$CFG->libdir/pdflib.php");
		require_once($CFG->dirroot.'/mod/certificate/lib.php');
		require_once($CFG->libdir.'/adodb/adodb.inc.php');
		$cert_path = '/path/to/save/certificates/in';
		echo "------------- Finding New Certificates ------------------\n\n";

		$courseids = $DB->get_records_sql("SELECT DISTINCT c.id FROM certificate module, {course} c WHERE module.course=c.id");
		$module = $DB->get_record('modules', array('name' => 'certificate'));
		$courses = array();
		if (!empty($courseids)) {
			foreach ($courseids as $courseid) {
				$courses[$courseid->id] = $DB->get_record('course', array('id'=> $courseid->id));
				if ($courses[$courseid->id]->visible != 1) {
					unset($courses[$courseid->id]);
				}
			}
		}
		foreach ($courses as $cid=>$course) {
			$COURSE = $course;
			echo "\n\n--------------------------\nCourse: {$course->shortname}\n";
			$students = $DB->get_records_sql("SELECT usr.id, usr.idnumber, usr.firstname, usr.lastname, usr.email, timecompleted, finalgrade
				FROM {course} c
				INNER JOIN {context} cx ON c.id = cx.instanceid
				AND cx.contextlevel = '50' and c.id=?
				INNER JOIN {role_assignments} ra ON cx.id = ra.contextid
				INNER JOIN {role} r ON ra.roleid = r.id
				INNER JOIN {user} usr ON ra.userid = usr.id
				INNER JOIN {course_completions} comp ON usr.id=comp.userid AND c.id=comp.course
				INNER JOIN {grade_items} i on i.courseid=c.id AND i.itemtype='course'
				INNER JOIN {grade_grades} g ON g.itemid=i.id AND g.userid=usr.id
				WHERE r.name = 'Student'
				ORDER BY usr.firstname, c.fullname", array($cid));
			$certmods = $DB->get_records("course_modules", array('course'=>$cid, 'module'=>$module->id));
			foreach ($certmods as $mod) {
				$certificate = $DB->get_record("certificate", array('id'=>$mod->instance));
				echo "\nCERT: {$certificate->name}\n";
				$cert_count = 0;
				foreach ($students as $student) {
					$modinfo = get_fast_modinfo($course, $student->id);
					$cm = $modinfo->get_cm($mod->id);
					$context = get_context_instance(CONTEXT_MODULE, $cm->id);

					// now create any certs
					$ci = new condition_info($cm);
					$available = $ci->is_available($_info, false, $student->id);
					if ($available) {
						$certrecord = certificate_prepare_issue($course, $student, $certificate);
						$strreviewcertificate = get_string('reviewcertificate', 'certificate');
						$strgetcertificate = get_string('getcertificate', 'certificate');
						$strgrade = get_string('grade', 'certificate');
						$strcoursegrade = get_string('coursegrade', 'certificate');
						$strcredithours = get_string('credithours', 'certificate');
						$filename = clean_filename($certificate->name.'.pdf');

						$USER = $student;
						//// this file initiates and populates the $pdf object
						require ("$CFG->dirroot/mod/certificate/type/$certificate->certificatetype/certificate.php");
						certificate_issue($course, $certificate, $certrecord, $cm); // update certrecord as issued
						$file_path = "{$cert_path}/{$enrollment_id}/{$filename}"; // TODO: This probably needs to be changed 
						$file_contents = $pdf->Output($file_path, 'F');
						$cert_count++;
					}
				}
				echo "Generated $cert_count Certificates\n\n";
			}

		}
		echo "\n\n------------- END Local Plugin ------------------\n";
}

