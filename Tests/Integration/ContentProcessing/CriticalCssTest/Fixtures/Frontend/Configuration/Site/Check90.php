<?php
return [
    'accelerator' => [
        'criticalCss' => [
            'enable' => false,
            'filesForLayout' => [
                'home' => [
                    'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/criticalOne.css',
                    'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/criticalTwo.css'
                ]
            ],
            'filesToRemoveWhenActive' => [
                'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/removeOne.css',
            ]
        ]
    ]
];
