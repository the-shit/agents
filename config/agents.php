<?php

return [
    'kits' => [
        'architect' => [
            'description' => 'System design and architecture planning',
            'capabilities' => [
                'Design system architecture',
                'Define component structure',
                'Plan data flows',
                'Create technical specifications',
            ],
            'tasks' => [
                'analyze_requirements',
                'design_architecture',
                'define_interfaces',
                'create_diagrams',
            ],
        ],

        'implementer' => [
            'description' => 'Code implementation and development',
            'capabilities' => [
                'Write production code',
                'Implement features',
                'Refactor code',
                'Optimize performance',
            ],
            'tasks' => [
                'implement_features',
                'write_tests',
                'refactor_code',
                'optimize_performance',
            ],
        ],

        'tester' => [
            'description' => 'Testing and quality assurance',
            'capabilities' => [
                'Write test suites',
                'Run automated tests',
                'Perform integration testing',
                'Validate code quality',
            ],
            'tasks' => [
                'write_unit_tests',
                'write_integration_tests',
                'run_test_suites',
                'validate_coverage',
            ],
        ],

        'reviewer' => [
            'description' => 'Code review and quality control',
            'capabilities' => [
                'Review code changes',
                'Enforce coding standards',
                'Identify security issues',
                'Provide feedback',
            ],
            'tasks' => [
                'review_pull_requests',
                'check_code_quality',
                'verify_security',
                'provide_feedback',
            ],
        ],
    ],
];
