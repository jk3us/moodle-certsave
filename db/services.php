<?php 
$services = array(
      'Cure4Kids Functions' => array(                                                //the name of the web service
          'functions' => array ('local_c4k_my_certificates'), //web service functions of this service
          'requiredcapability' => '',                //if set, the web service user need this capability to access 
                                                                              //any function of this service. For example: 'some/capability:specified'                 
          'restrictedusers' =>1,                                             //if enabled, the Moodle administrator must link some user to this service
                                                                              //into the administration
          'enabled'=>1,                                                       //if enabled, the service can be reachable on a default installation
       )
  );

$functions = array(
    'local_c4k_my_certificates' => array(         //web service function name
        'classname'   => 'local_c4k_external',  //class containing the external function
        'methodname'  => 'my_certificates',          //external function name
        'classpath'   => 'local/c4k/externallib.php',  //file containing the class/external function
        'description' => 'Get my certificates.',    //human readable description of the web service function
        'type'        => 'read',                  //database rights of the web service function (read, write)
    ),
);
