<?php require_once __DIR__ . '/app/crest.php';

/*
 * MIT License
 */

$statusNewID = 1243; // значение пользовательского поля компании
$statusReturnID = 1247; // значение пользовательского поля компании
$reservedUser = 71; // резервный аккаунт
$totalEnabledCompanies = 150; // количество разрешённых компаний
switch ($_REQUEST['event']) {
    
    // обработчик событий на добавление и изменение компании
    case 'ONCRMCOMPANYADD':
    case 'ONCRMCOMPANYUPDATE':
        $companyID = $_REQUEST['data']['FIELDS']['ID'];
        $getNeedleCompanies = CRest::callBatch([
                    'get_company' => [
                        'method' => 'crm.company.get',
                        'params' => ['id' => $companyID]
                    ],
                    'get_assigned' => [
                        'method' => 'user.get',
                        'params' => [
                            'FILTER' => [
                                "ID" => '$result[get_company][ASSIGNED_BY_ID]'
                            ]
                        ]
                    ],
                    'get_companies_on_new_status' => [
                        'method' => 'crm.company.list',
                        'params' => [
                            'filter' => [
                                "ASSIGNED_BY_ID" => '$result[get_company][ASSIGNED_BY_ID]',
                                'UF_CRM_CLIENT_STATUS' => $statusNewID
                            ]
                        ]
                    ],
                    'get_companies_on_return_status' => [
                        'method' => 'crm.company.list',
                        'params' => [
                            'filter' => [
                                "ASSIGNED_BY_ID" => '$result[get_company][ASSIGNED_BY_ID]',
                                'UF_CRM_CLIENT_STATUS' => $statusReturnID
                            ]
                        ]
                    ]
        ]);
        
        $total = $getNeedleCompanies['result']['result_total']['get_companies_on_new_status'] + $getNeedleCompanies['result']['result_total']['get_companies_on_return_status'];
        $managerID = $getNeedleCompanies['result']['result']['get_company']['ASSIGNED_BY_ID'];
        $managerName = $getNeedleCompanies['result']['result']['get_assigned'][0]['NAME'] . ' ' . $getNeedleCompanies['result']['result']['get_assigned'][0]['LAST_NAME'];
        $companyTitle = $getNeedleCompanies['result']['result']['get_company']['TITLE'];
        
        /*
         * Проверка на выполнение условий (одновременно):
         * 1. резервный аккаунт не является ответственным
         * 2. по нужным параметрам количество компаний не превышает количество разрешённых компаний
         * 3. нужные параметры указаны в карточке компании
         * 
         * Если все условия верны, то компания переводится на резервный аккаунт, а сотруднику отправляется уведомление 
         */
        if (
                intval($managerID) !== intval($reservedUser) && 
                ($total > $totalEnabledCompanies || $logData['new_companies_count'] > $totalEnabledCompanies || $logData['return_companies_count'] > $totalEnabledCompanies) && 
                (intval($getNeedleCompanies['result']['result']['get_company']['UF_CRM_CLIENT_STATUS']) == $statusNewID || intval($getNeedleCompanies['result']['result']['get_company']['UF_CRM_CLIENT_STATUS']) == $statusReturnID)
        ) {
            $action = CRest::callBatch([
                        'update_company' => [
                            'method' => 'crm.company.update',
                            'params' => [
                                'id' => $companyID,
                                'fields' => [
                                    'ASSIGNED_BY_ID' => $reservedUser
                                ]
                            ]
                        ],
                        'send_notification' => [
                            'method' => 'im.notify',
                            'params' => [
                                'to' => $managerID,
                                'message' => '[br]' . $managerName . ',[br]'
                                . 'Допустимый лимит компаний по данным параметрам исчерпан[br][br]'
                                . 'Компания [URL=/crm/company/details/' . $companyID . '/]' . $companyTitle . '[/URL] переведена на [URL=/company/personal/user/71/]резервный аккаунт[/URL][br][br]'
                                . 'Если вы желаете продолжить работу с данной компанией, скиньте на этот аккаунт другие компании[br][br]'
                                . 'С уважением, Администрация портала',
                                'type' => 'SYSTEM'
                            ]
                        ]
            ]);
            break;
        }
}  