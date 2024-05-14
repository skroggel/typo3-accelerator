<?php
return [
    'accelerator' => [
        'criticalCss' => [
            'enable' => true,
            'filesForLayout' => [
                'home' => [
                    'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/criticalOne.css',
                    'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/criticalTwo.css'
                ]
            ],
            'filesToRemoveWhenActive' => [
                'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/removeOne.css'
            ]
        ]
    ]
];
