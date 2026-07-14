<?php

/**
 * Department filter keys for organizer calendar (must match sidebar data-dept values).
 *
 * @return array<string, string> value => label
 */
function eventify_organizer_department_choices(): array
{
    return [
        'ALL' => 'All Departments',
        'High school department' => 'High School Department',
        'College of Communication, Information and Technology' => 'College of Communication, Information and Technology',
        'College of Accountancy and Business' => 'College of Accountancy and Business',
        'School of Law and Political Science' => 'School of Law and Political Science',
        'College of Education' => 'College of Education',
        'College of Nursing and Allied health sciences' => 'College of Nursing and Allied health sciences',
        'College of Hospitality Management' => 'College of Hospitality Management',
    ];
}
