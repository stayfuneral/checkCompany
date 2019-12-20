<?php require_once __DIR__ . '/app/crest.php';

/*
 * MIT License
 */

$eventHandler = ($_SERVER['SERVER_PORT']==443||$_SERVER["HTTPS"]=="on"? 'https': 'http')."://".$_SERVER['SERVER_NAME'].(in_array($_SERVER['SERVER_PORT'], Array(80, 443))?'':':'.$_SERVER['SERVER_PORT']).$_SERVER['SCRIPT_NAME']; //адрес обработчика, возвращает текущую страницу
$statusNewID = 1243; // значение пользовательского поля компании
$statusReturnID = 1247; // значение пользовательского поля компании
$reservedUser = 71; // резервный аккаунт

switch ($_REQUEST['event']) {
    
    // установка приложения, регистрация обработчика событий
    case 'ONAPPINSTALL':
        $result = CRest::installApp();
        $eventBind = CRest::callBatch([
                    'set_event_onCrmCompanyAdd' => [
                        'method' => 'event.bind',
                        'params' => [
                            'event' => 'onCrmCompanyAdd',
                            'handler' => $eventHandler
                        ]
                    ],
                    'set_event_onCrmCompanyUpdate' => [
                        'method' => 'event.bind',
                        'params' => [
                            'event' => 'onCrmCompanyUpdate',
                            'handler' => $eventHandler
                        ]
                    ]
        ]);
        break;
    
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
        
        /*
         * Проверка на выполнение условий (одновременно):
         * 1. резервный аккаунт не является ответственным
         * 2. по нужным параметрам количество компаний не превышает 150
         * 3. нужные параметры указаны в карточке компании
         * 
         * Если все условия верны, то компания переводится на резервный аккаунт, а сотруднику отправляется уведомление 
         */
        if (
                intval($getNeedleCompanies['result']['result']['get_company']['ASSIGNED_BY_ID']) !== intval($reservedUser) && ($total > 150 || $logData['new_companies_count'] > 150 || $logData['return_companies_count'] > 150) && (intval($getNeedleCompanies['result']['result']['get_company']['UF_CRM_CLIENT_STATUS']) == $statusNewID || intval($getNeedleCompanies['result']['result']['get_company']['UF_CRM_CLIENT_STATUS']) == $statusReturnID)
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
                                'to' => $getNeedleCompanies['result']['result']['get_company']['ASSIGNED_BY_ID'],
                                'message' => '[br]' . $getNeedleCompanies['result']['result']['get_assigned'][0]['NAME'] . ' ' . $getNeedleCompanies['result']['result']['get_assigned'][0]['LAST_NAME'] . ',[br]'
                                . 'Допустимый лимит компаний по данным параметрам исчерпан[br][br]'
                                . 'Компания [URL=/crm/company/details/' . $getNeedleCompanies['result']['result']['get_company']['ID'] . '/]' . $getNeedleCompanies['result']['result']['get_company']['TITLE'] . '[/URL] переведена на [URL=/company/personal/user/71/]резервный аккаунт[/URL][br][br]'
                                . 'Если вы желаете продолжить работу с данной компанией, скиньте на этот аккаунт другие компании[br][br]'
                                . 'С уважением, Администрация портала',
                                'type' => 'SYSTEM'
                            ]
                        ]
            ]);
            break;
        }
}  