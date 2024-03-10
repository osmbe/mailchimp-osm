<?php
/**
 * Script for updating the data cache.
 *
 * Can also be invoked as `composer update-data-cache`.
 */

declare(strict_types=1);

chdir(__DIR__.'/../');

require 'vendor/autoload.php';

$config = include 'config/config.php';

if (!isset($config['apiKey'])) {
    echo 'No Mailchimp API key found'.PHP_EOL;
    exit(0);
}

$mailchimp = new \MailchimpAPI\Mailchimp($config['apiKey']);

$time_start = microtime(true);

$mailchimpLists = $mailchimp
    ->lists()
    ->get()
    ->deserialize()
    ->lists;

$myLists = [];
foreach ($mailchimpLists as $list) {
    if (isset($config['lists'])) {
        $configList = current(array_filter($config['lists'], function ($value) use ($list) {
            return $value['id'] === $list->id;
        }));

        $identifier = array_search($configList, $config['lists']);

        if ($identifier === false) {
            continue;
        }
    }

    $myList = [
        'id'                 => $list->id,
        'name'               => $list->name,
        'doubleOptIn'        => $list->double_optin,
        'subscribeURL'       => $list->subscribe_url_long,
        'identifier'         => $identifier ?? $list->id,
        'description'        => $configList['description'] ?? null,
        'displayList'        => $configList['displayList'] ?? false,
        'disableForm'        => $configList['disableForm'] ?? false,
        'usernameMergeField' => $configList['username'] ?? null,
        'countries'          => $configList['countries'] ?? [],
        'members'            => null,
        'mergeFields'        => [],
        'interestCategories' => [],
    ];

    /**
     * Get merge fields (sorted by display order).
     */
    $mailchimpMergeFields = $mailchimp
        ->lists($list->id)
        ->mergeFields()
        ->get()
        ->deserialize()
        ->merge_fields;
    foreach ($mailchimpMergeFields as $mergeField) {
        if (in_array($mergeField->tag, ['ADDRESS', 'POSTCODE'])) continue;

        $myList['mergeFields'][] = [
            'tag'          => $mergeField->tag,
            'name'         => $mergeField->name,
            'type'         => $mergeField->type,
            'required'     => $mergeField->required,
            'defaultValue' => $mergeField->default_value,
            'public'       => $mergeField->public,
            'displayOrder' => $mergeField->display_order,
            'helpText'     => $mergeField->help_text,
            'size'         => $mergeField->options->size ?? null,
            'choices'      => $mergeField->options->choices ?? null,
        ];
    }
    $displayOrder = array_column($myList['mergeFields'], 'displayOrder');
    array_multisort($displayOrder, SORT_ASC, $myList['mergeFields']);

    /**
     * Get interest categories and interests (sorted by display order).
     */
    $mailchimpInterestCategories = $mailchimp
        ->lists($list->id)
        ->interestCategories()
        ->get()
        ->deserialize()
        ->categories;
    foreach ($mailchimpInterestCategories as $category) {
        $mailchimpInterests = $mailchimp
            ->lists($list->id)
            ->interestCategories($category->id)
            ->interests()
            ->get()
            ->deserialize()
            ->interests;

        $myInterests = [];
        foreach ($mailchimpInterests as $interest) {
            $myInterests[] = [
                'id'           => $interest->id,
                'name'         => $interest->name,
                'displayOrder' => $interest->display_order,
            ];
        }
        $interestsDisplayOrder = array_column($myInterests, 'displayOrder');
        array_multisort($interestsDisplayOrder, SORT_ASC, $myInterests);

        $myList['interestCategories'][] = [
            'title'        => $category->title,
            'displayOrder' => $category->display_order,
            'type'         => $category->type,
            'interests'    => $myInterests,
        ];
    }
    $categoriesDisplayOrder = array_column($myList['interestCategories'], 'displayOrder');
    array_multisort($categoriesDisplayOrder, SORT_ASC, $myList['interestCategories']);

    /*
     * Get list members
     */
    if ($myList['displayList'] !== true) {
        if (file_exists(sprintf('data/cache/members-%s.json', $myList['identifier']))) {
            unlink(sprintf('data/cache/members-%s.json', $myList['identifier']));
        }
    } else {
        $mailchimpMembers = $mailchimp
            ->lists($list->id)
            ->members()
            ->get([
                'count'  => '999',
                'status' => 'subscribed',
            ])
            ->deserialize()
            ->members;

        $myList['members'] = count($mailchimpMembers);

        $myMembers = [];
        foreach ($mailchimpMembers as $member) {
            $myMember = [
                'mergeFields'  => (array) $member->merge_fields,
                'timestampOpt' => strtotime($member->timestamp_opt),
            ];

            if (!is_null($myList['usernameMergeField'])) {
                $myMember['username'] = $myMember['mergeFields'][$myList['usernameMergeField']];

                if (!empty($myMember['username'])) {
                    try {
                        $client = new \GuzzleHttp\Client();

                        $res = $client->request(
                            'GET',
                            sprintf('https://hdyc.neis-one.org/search/%s', $myMember['username'])
                        );

                        $hdyc = json_decode((string) $res->getBody());
                    } catch (\GuzzleHttp\Exception $e) {
                        printf('%s (user: %s)%s', $e->getMessage(), $myMember['username'], PHP_EOL);
                    }

                    if (isset($hdyc->contributor)) {
                        $myMember['since'] = $hdyc->contributor->since ?? null;
                    }
                    if (isset($hdyc->changesets)) {
                        $myMember['changesTotal'] = isset($hdyc->changesets->changes) ? intval($hdyc->changesets->changes) : 0;
                        $myMember['changesetsTotal'] = isset($hdyc->changesets->no) ? intval($hdyc->changesets->no) : 0;
                    }
                    if (isset($hdyc->countries, $hdyc->countries->countries) && count($myList['countries']) > 0) {
                        $myMember['changesLocal'] = [];
                        $myMember['changesetsLocal'] = [];

                        $countries = array_map(
                            function ($country) {
                                return explode('=', $country);
                            },
                            explode(';', $hdyc->countries->countries)
                        );

                        $codes = array_column($countries, 1);

                        foreach ($myList['countries'] as $c) {
                            $key = array_search($c, $codes);

                            if ($key === false) {
                                $myMember['changesLocal'][$c] = 0;
                                $myMember['changesetsLocal'][$c] = 0;
                            } else {
                                $myMember['changesLocal'][$c] = intval($countries[$key][3]);
                                $myMember['changesetsLocal'][$c] = intval($countries[$key][2]);
                            }
                        }
                    }
                }
            }

            $myMembers[] = $myMember;
        }

        if (isset($configList['orderBy'])) {
            if (!is_array($configList['orderBy'])) {
                $configList['orderBy'] = [$configList['orderBy']];
            }

            $fields = array_column($myMembers, 'mergeFields');

            $order = [];
            foreach ($configList['orderBy'] as $o) {
                $order[] = array_column((array) $fields, $o);
                $order[] = SORT_ASC;
                $order[] = SORT_STRING | SORT_FLAG_CASE;
            }

            $multisort = array_merge($order, [&$myMembers]);
            call_user_func_array('array_multisort', $multisort);
        }

        if (file_exists(sprintf('data/cache/members-%s.json', $myList['identifier']))) {
            unlink(sprintf('data/cache/members-%s.json', $myList['identifier']));
        }
        file_put_contents(sprintf('data/cache/members-%s.json', $myList['identifier']), json_encode($myMembers));
    }

    $myLists[] = $myList;
}

if (file_exists('data/cache/lists.json')) {
    unlink('data/cache/lists.json');
}
file_put_contents('data/cache/lists.json', json_encode($myLists));

$time_end = microtime(true);
$time = $time_end - $time_start;

printf(
    'Lists cache updated : %d (%.2f seconds)%s',
    count($myLists),
    $time,
    PHP_EOL
);

exit(0);
