<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Exam pack sync
    |--------------------------------------------------------------------------
    | Subject content is uploaded as .zip and extracted under storage/app/private.
    | Database tables (exam_*) store catalog + sync metadata only — not MCQ bodies.
    */

    'max_upload_kb' => 51200, // 50 MB

];
