<?php
return [
    'accelerator' => [
        'criticalCss' => [
            'enable' => true,
            'filesForPath' => [
                '^/test/.+$' => [
                    'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/criticalOne.css',
                    'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/criticalTwo.css'
                ]
            ],
        ]
    ]
];
