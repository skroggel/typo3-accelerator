<?php
return [
    'accelerator' => [
        'criticalCss' => [
            'enable' => true,
            'filesForPage' => [
                '1,2,3' => [
                    'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/criticalOne.css',
                    'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/criticalTwo.css'
                ]
            ],
        ]
    ]
];
