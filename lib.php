<?php

function local_c4k_cron() {
		global $DB,$CFG;
		require_once($CFG->libdir.'/conditionlib.php');
		require_once("$CFG->libdir/pdflib.php");
		require_once($CFG->dirroot.'/mod/certificate/lib.php');
		require_once($CFG->libdir.'/adodb/adodb.inc.php');
		$local_db = local_c4k_db_init();
		$cert_path = $CFG->c4k_file_root.'/../private/classrooms_certificates';
		echo "------------- C4K Local Plugin ------------------\n\n";

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
			$exists = $local_db->Execute("SELECT id FROM classrooms_summary WHERE course_id=?", array($course->id));
			if ($exists->RecordCount() == 0) { // add to classroom summary.  basic info is fine for now
				$course_data = array(
					'course_id'=>$course->id,
					'title' => $course->fullname,
					'idnumber' => $course->idnumber,
					'list_doctype' => 'file',
					'file' => 'none', // will be updated later
					'file_type' => 'application/pdf; charset=binary',
					'original_filename' => $filename
				);
				$rs = $local_db->AutoExecute("classrooms_summary", $course_data, 'INSERT');
			}
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
			foreach ($students as $student) {
				// first add/update enrollment info
				$exists = $local_db->Execute("SELECT id FROM classrooms_enrollments WHERE course_id=? AND moodle_user_id=?", array($course->id, $student->id));
				if ($exists->RecordCount() == 0) { // add to classroom summary.  basic info is fine for now
					$enrollment_data = array(
						'course_id' => $course->id,
						'moodle_user_id' => $student->id,
						'date' => date('Y-m-d H:i:s'),
						'grade'=>$student->finalgrade,
						'complete_date'=>$student->timecompleted?date('Y-m-d h:i:s',$student->timecompleted):null,
					);
					$rs = $local_db->AutoExecute("classrooms_enrollments", $enrollment_data, 'INSERT');
				} else {
					$enrollment_id = $exists->fetchRow();
					$enrollment_id =  $enrollment_id['id'];
					$rs = $local_db->AutoExecute("classrooms_enrollments", array('grade'=>$student->finalgrade,'complete_date'=>$student->timecompleted?date('Y-m-d h:i:s',$student->timecompleted):null), 'UPDATE', "id={$enrollment_id}");
				}
			}
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
					$enrollment_id = $local_db->GetOne("SELECT id FROM classrooms_enrollments WHERE course_id=? AND moodle_user_id=?", array($course->id, $student->id));
					$exists = $local_db->Execute("SELECT id FROM classrooms_certificates WHERE enrollment_id=?", array($enrollment_id));
					if ($exists->RecordCount() > 0) continue;
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
						$certificate_data = array(
							'enrollment_id'=> $enrollment_id,
							'certificate_id' => $certificate->id,
							'title' => $certificate->name,
							'date' => date('Y-m-d H:i:s'),
							'file' => 'none', // will be updated later
						);
						$rs = $local_db->AutoExecute("classrooms_certificates", $certificate_data, 'INSERT');
						$file_id = $local_db->Insert_ID();
						$filename = "{$file_id}_{$filename}";
						$file_path = "{$cert_path}/{$filename}";
						$file_contents = $pdf->Output($file_path, 'F');
						$certificate_data = array(
							'file' => $filename,
						);
						$rs = $local_db->AutoExecute("classrooms_certificates", $certificate_data, 'UPDATE',"id={$file_id}");
						// User file in place, now add the transcript entry
						//$transcript_info = array(
						//	'user_id' => $student->idnumber,
						//	'title' => $certificate->name,
						//	'type' => 'classrooms',
						//	'host_org' => 'Cure4Kids',
						//	'file_id' => $file_id,
						//	'notes' => $certificate->id,
						//	'end_date' => date('Y-m-d H:i:s'),
						//);
						//$rs = $local_db->AutoExecute("users_transcript_entries", $transcript_info, 'INSERT');
						$cert_count++;
					}
				}
				echo "Generated $cert_count Certificates\n\n";
			}

		}
		echo "\n\n------------- END Local Plugin ------------------\n";
		$local_db->Close();
}

function local_c4k_db_init() {
    // Connect to the external database (forcing new connection)
	global $CFG;
    $local_db = ADONewConnection('mysql');
    if (false) {
        $local_db->debug = true;
        ob_start();//start output buffer to allow later use of the page headers
    }
    $local_db->Connect($CFG->c4k_db_host, $CFG->c4k_db_user, $CFG->c4k_db_pass, $CFG->c4k_db_name, true);
    $local_db->SetFetchMode(ADODB_FETCH_ASSOC);
    $local_db->Execute("SET NAMES 'utf8'");

    return $local_db;
}
