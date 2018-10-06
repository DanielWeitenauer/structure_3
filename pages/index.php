<?php

/**
 * @package redaxo5
 */

$structure_data = rex_structure_data::getInstance();

// basic request vars
$category_id = $structure_data->getCategoryId();
$article_id = $structure_data->getArticleId();
$clang = $structure_data->getClangId();

// --------------------- Extension Point
echo rex_extension::registerPoint(new rex_extension_point('PAGE_STRUCTURE_HEADER_PRE', '', [
    'context' => $structure_data->getContext(),
]));

// --------------------------------------------- TITLE
echo rex_view::title(rex_i18n::msg('title_structure'));

// --------------------------------------------- Languages
echo rex_view::clangSwitchAsButtons($structure_data->getContext());

// --------------------------------------------- Path
require __DIR__ . '/../functions/function_rex_category.php';

// -------------- STATUS_TYPE Map
$catStatusTypes = rex_category_service::statusTypes();
$artStatusTypes = rex_article_service::statusTypes();

// --------------------------------------------- API MESSAGES
echo rex_api_function::getMessage();

// --------------------------------------------- KATEGORIE LISTE
$cat_name = rex_i18n::msg('root_level');
$category = rex_category::get($structure_data->getCategoryId(), $structure_data->getClangId());
if ($category) {
    $cat_name = $category->getName();
}

$data_colspan = 5;

// --------------------- Extension Point
echo rex_extension::registerPoint(new rex_extension_point('PAGE_STRUCTURE_HEADER', '', [
    'category_id' => $structure_data->getCategoryId(),
    'clang' => $structure_data->getClangId(),
]));

// --------------------- SEARCH BAR
//require_once $this->getPath('functions/function_rex_searchbar.php');
//echo rex_structure_searchbar($structure_data->getContext());

// --------------------- COUNT CATEGORY ROWS

$KAT = rex_sql::factory();
// $KAT->setDebug();
if (count($structure_data->getMountpoints()) > 0 && $structure_data->getCategoryId() == 0) {
    $parent_id = implode(',', $structure_data->getMountpoints());
    $KAT->setQuery('SELECT COUNT(*) as rowCount FROM ' . rex::getTablePrefix() . 'article WHERE id IN (' . $parent_id . ') AND startarticle=1 AND clang_id=' . $structure_data->getClangId());
} else {
    $KAT->setQuery('SELECT COUNT(*) as rowCount FROM ' . rex::getTablePrefix() . 'article WHERE parent_id=' . $structure_data->getCategoryId() . ' AND startarticle=1 AND clang_id=' . $structure_data->getClangId());
}

// --------------------- ADD PAGINATION

$catPager = new rex_pager(30, 'catstart');
$catPager->setRowCount($KAT->getValue('rowCount'));
$catFragment = new rex_fragment();
$catFragment->setVar('urlprovider', $structure_data->getContext());
$catFragment->setVar('pager', $catPager);
echo $catFragment->parse('core/navigations/pagination.php');

// --------------------- GET THE DATA

if (count($structure_data->getMountpoints()) > 0 && $structure_data->getCategoryId() == 0) {
    $parent_id = implode(',', $structure_data->getMountpoints());

    $KAT->setQuery('SELECT parent_id FROM ' . rex::getTable('article') . ' WHERE id IN (' . $parent_id . ') GROUP BY parent_id');
    $orderBy = $KAT->getRows() > 1 ? 'catname' : 'catpriority';

    $KAT->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'article WHERE id IN (' . $parent_id . ') AND startarticle=1 AND clang_id=' . $structure_data->getClangId() . ' ORDER BY ' . $orderBy . ' LIMIT ' . $catPager->getCursor() . ',' . $catPager->getRowsPerPage());
} else {
    $KAT->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'article WHERE parent_id=' . $structure_data->getCategoryId() . ' AND startarticle=1 AND clang_id=' . $structure_data->getClangId() . ' ORDER BY catpriority LIMIT ' . $catPager->getCursor() . ',' . $catPager->getRowsPerPage());
}

$echo = '';

// --------------------- KATEGORIE ADD FORM

if ($structure_data->getFunction() == 'add_cat' && $structure_data->getCatPerm()) {
    $meta_buttons = rex_extension::registerPoint(new rex_extension_point('CAT_FORM_BUTTONS', '', [
        'id' => $structure_data->getCategoryId(),
        'clang' => $structure_data->getClangId(),
    ]));
    $add_buttons = rex_api_category_add::getHiddenFields().'
        <input type="hidden" name="parent-category-id" value="' . $structure_data->getCategoryId() . '" />
        <button class="btn btn-save" type="submit" name="category-add-button"' . rex::getAccesskey(rex_i18n::msg('add_category'), 'save') . '>' . rex_i18n::msg('add_category') . '</button>';

    $class = 'mark';

    $echo .= '
                <tr class="' . $class . '">
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-category"></i></td>
                    <td class="rex-table-id" data-title="' . rex_i18n::msg('header_id') . '">-</td>
                    <td data-title="' . rex_i18n::msg('header_category') . '"><input class="form-control" type="text" name="category-name" class="rex-js-autofocus" autofocus /></td>
                    <td class="rex-table-priority" data-title="' . rex_i18n::msg('header_priority') . '"><input class="form-control" type="text" name="category-position" value="' . ($catPager->getRowCount() + 1) . '" /></td>
                    <td class="rex-table-action">' . $meta_buttons . '</td>
                    <td class="rex-table-action" colspan="2">' . $add_buttons . '</td>
                </tr>';

    // ----- EXTENSION POINT
    $echo .= rex_extension::registerPoint(new rex_extension_point('CAT_FORM_ADD', '', [
        'id' => $structure_data->getCategoryId(),
        'clang' => $structure_data->getClangId(),
        'data_colspan' => ($data_colspan + 1),
    ]));
}

// --------------------- KATEGORIE LIST
if ($KAT->getRows() > 0) {
    for ($i = 0; $i < $KAT->getRows(); ++$i) {
        $i_category_id = $KAT->getValue('id');

        $kat_link = $structure_data->getContext()->getUrl(['category_id' => $i_category_id]);
        $kat_icon_td = '<td class="rex-table-icon"><a href="' . $kat_link . '" title="' . htmlspecialchars($KAT->getValue('catname')) . '"><i class="rex-icon rex-icon-category"></i></a></td>';

        $kat_status = $catStatusTypes[$KAT->getValue('status')][0];
        $status_class = $catStatusTypes[$KAT->getValue('status')][1];
        $status_icon = $catStatusTypes[$KAT->getValue('status')][2];

        if ($structure_data->getCatPerm()) {
            if ($structure_data->getCatPerm() && rex::getUser()->hasPerm('publishCategory[]')) {
                $kat_status = '<a class="' . $status_class . '" href="' . $structure_data->getContext()->getUrl(['category-id' => $i_category_id, 'catstart' => $structure_data->getCatStart()] + rex_api_category_status::getUrlParams()).'"><i class="rex-icon '.$status_icon.'"></i> '.$kat_status.'</a>';
            } else {
                $kat_status = '<span class="' . $status_class . ' text-muted"><i class="rex-icon ' . $status_icon . '"></i> ' . $kat_status . '</span>';
            }

            if ($structure_data->getEditId() == $i_category_id && $structure_data->getFunction() == 'edit_cat') {
                // --------------------- KATEGORIE EDIT FORM

                // ----- EXTENSION POINT
                $meta_buttons = rex_extension::registerPoint(new rex_extension_point('CAT_FORM_BUTTONS', '', [
                    'id' => $structure_data->getEditId(),
                    'clang' => $structure_data->getClangId(),
                ]));

                $add_buttons = rex_api_category_edit::getHiddenFields().'
                <input type="hidden" name="category-id" value="' . $structure_data->getEditId() . '" />
                <button class="btn btn-save" type="submit" name="category-edit-button"' . rex::getAccesskey(rex_i18n::msg('save_category'), 'save') . '>' . rex_i18n::msg('save_category') . '</button>';

                $class = 'mark';
                if ($meta_buttons != '') {
                    $class .= ' rex-has-metainfo';
                }

                $echo .= '
                    <tr class="' . $class . '">
                        ' . $kat_icon_td . '
                        <td class="rex-table-id" data-title="' . rex_i18n::msg('header_id') . '">' . $i_category_id . '</td>
                        <td data-title="' . rex_i18n::msg('header_category') . '"><input class="form-control" type="text" name="category-name" value="' . htmlspecialchars($KAT->getValue('catname')) . '" class="rex-js-autofocus" autofocus /></td>
                        <td class="rex-table-priority" data-title="' . rex_i18n::msg('header_priority') . '"><input class="form-control" type="text" name="category-position" value="' . htmlspecialchars($KAT->getValue('catpriority')) . '" /></td>
                        <td class="rex-table-action">' . $meta_buttons . '</td>
                        <td class="rex-table-action" colspan="2">' . $add_buttons . '</td>
                    </tr>';

                // ----- EXTENSION POINT
                $echo .= rex_extension::registerPoint(new rex_extension_point('CAT_FORM_EDIT', '', [
                    'id' => $structure_data->getEditId(),
                    'clang' => $structure_data->getClangId(),
                    'category' => $KAT,
                    'catname' => $KAT->getValue('catname'),
                    'catpriority' => $KAT->getValue('catpriority'),
                    'data_colspan' => ($data_colspan + 1),
                ]));
            } else {
                // --------------------- KATEGORIE WITH WRITE

                $category_delete = '<a href="' . $structure_data->getContext()->getUrl(['category-id' => $i_category_id, 'catstart' => $structure_data->getCatStart()] + rex_api_category_delete::getUrlParams()).'" data-confirm="'.rex_i18n::msg('delete').' ?"><i class="rex-icon rex-icon-delete"></i> '.rex_i18n::msg('delete').'</a>';

                $echo .= '
                    <tr>
                        ' . $kat_icon_td . '
                        <td class="rex-table-id" data-title="' . rex_i18n::msg('header_id') . '">' . $i_category_id . '</td>
                        <td data-title="' . rex_i18n::msg('header_category') . '"><a href="' . $kat_link . '">' . htmlspecialchars($KAT->getValue('catname')) . '</a></td>
                        <td class="rex-table-priority" data-title="' . rex_i18n::msg('header_priority') . '">' . htmlspecialchars($KAT->getValue('catpriority')) . '</td>
                        <td class="rex-table-action"><a href="' . $structure_data->getContext()->getUrl(['edit_id' => $i_category_id, 'function' => 'edit_cat', 'catstart' => $structure_data->getCatStart()]).'"><i class="rex-icon rex-icon-edit"></i> '.rex_i18n::msg('change').'</a></td>
                        <td class="rex-table-action">' . $category_delete . '</td>
                        <td class="rex-table-action">' . $kat_status . '</td>
                    </tr>';
            }
        } elseif (rex::getUser()->getComplexPerm('structure')->hasCategoryPerm($i_category_id)) {
            // --------------------- KATEGORIE WITH READ

            $echo .= '
                    <tr>
                        ' . $kat_icon_td . '
                        <td class="rex-table-id" data-title="' . rex_i18n::msg('header_id') . '">' . $i_category_id . '</td>
                        <td data-title="' . rex_i18n::msg('header_category') . '"><a href="' . $kat_link . '">' . $KAT->getValue('catname') . '</a></td>
                        <td class="rex-table-priority" data-title="' . rex_i18n::msg('header_priority') . '">' . htmlspecialchars($KAT->getValue('catpriority')) . '</td>
                        <td class="rex-table-action"><span class="text-muted"><i class="rex-icon rex-icon-edit"></i> ' . rex_i18n::msg('change') . '</span></td>
                        <td class="rex-table-action"><span class="text-muted"><i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('delete') . '</span></td>
                        <td class="rex-table-action"><span class="' . $status_class . ' text-muted"><i class="rex-icon ' . $status_icon . '"></i> ' . $kat_status . '</span></td>
                    </tr>';
        }

        $KAT->next();
    }
}

$fragment = new rex_fragment();
$fragment->setVar('structure_data', $structure_data, false);
$fragment->setVar('content', $echo, false);
$echo = $fragment->parse('structure/table_categories.php');

$heading = rex_i18n::msg('structure_categories_caption', $cat_name);
if ($structure_data->getCategoryId() == 0) {
    $heading = rex_i18n::msg('structure_root_level_categories_caption');
}
$fragment = new rex_fragment();
$fragment->setVar('heading', $heading, false);
$fragment->setVar('content', $echo, false);
echo $fragment->parse('core/page/section.php');

// --------------------------------------------- ARTIKEL LISTE

$echo = '';

// --------------------- READ TEMPLATES

if ($structure_data->getCategoryId() > 0 || ($structure_data->getCategoryId() == 0 && !rex::getUser()->getComplexPerm('structure')->hasMountpoints())) {
    $withTemplates = $this->getPlugin('content')->isAvailable();
    $tmpl_head = '';
    if ($withTemplates) {
        $template_select = new rex_select();
        $template_select->setName('template_id');
        $template_select->setSize(1);
        $template_select->setStyle('class="form-control"');

        $templates = rex_template::getTemplatesForCategory($structure_data->getCategoryId());
        if (count($templates) > 0) {
            foreach ($templates as $t_id => $t_name) {
                $template_select->addOption(rex_i18n::translate($t_name, false), $t_id);
                $TEMPLATE_NAME[$t_id] = rex_i18n::translate($t_name);
            }
        } else {
            $template_select->addOption(rex_i18n::msg('option_no_template'), '0');
        }
        $TEMPLATE_NAME[0] = rex_i18n::msg('template_default_name');
        $tmpl_head = '<th>' . rex_i18n::msg('header_template') . '</th>';
    }

    // --------------------- ARTIKEL LIST

    // ---------- COUNT DATA
    $sql = rex_sql::factory();
    // $sql->setDebug();
    $sql->setQuery('SELECT COUNT(*) as artCount
                FROM
                    ' . rex::getTablePrefix() . 'article
                WHERE
                    ((parent_id=' . $structure_data->getCategoryId() . ' AND startarticle=0) OR (id=' . $structure_data->getCategoryId() . ' AND startarticle=1))
                    AND clang_id=' . $structure_data->getClangId() . '
                ORDER BY
                    priority, name');

    // --------------------- ADD PAGINATION

    $artPager = new rex_pager(30, 'artstart');
    $artPager->setRowCount($sql->getValue('artCount'));
    $artFragment = new rex_fragment();
    $artFragment->setVar('urlprovider', $structure_data->getContext());
    $artFragment->setVar('pager', $artPager);
    echo $artFragment->parse('core/navigations/pagination.php');

    // ---------- READ DATA
    $sql->setQuery('SELECT *
                FROM
                    ' . rex::getTablePrefix() . 'article
                WHERE
                    ((parent_id=' . $structure_data->getCategoryId() . ' AND startarticle=0) OR (id=' . $structure_data->getCategoryId() . ' AND startarticle=1))
                    AND clang_id=' . $structure_data->getClangId() . '
                ORDER BY
                    priority, name
                LIMIT ' . $artPager->getCursor() . ',' . $artPager->getRowsPerPage());

    // ----------- PRINT OUT THE ARTICLES

    // --------------------- ARTIKEL ADD FORM
    if ($structure_data->getFunction() == 'add_art' && $structure_data->getCatPerm()) {
        $tmpl_td = '';
        if ($withTemplates) {
            $selectedTemplate = 0;
            if ($structure_data->getCategoryId()) {
                // template_id vom Startartikel erben
                $sql2 = rex_sql::factory();
                $sql2->setQuery('SELECT template_id FROM ' . rex::getTablePrefix() . 'article WHERE id=' . $structure_data->getCategoryId() . ' AND clang_id=' . $structure_data->getClangId() . ' AND startarticle=1');
                if ($sql2->getRows() == 1) {
                    $selectedTemplate = $sql2->getValue('template_id');
                }
            }
            if (!$selectedTemplate || !isset($TEMPLATE_NAME[$selectedTemplate])) {
                $selectedTemplate = rex_template::getDefaultId();
            }
            if ($selectedTemplate && isset($TEMPLATE_NAME[$selectedTemplate])) {
                $template_select->setSelected($selectedTemplate);
            }

            $tmpl_td = '<td data-title="' . rex_i18n::msg('header_template') . '">' . $template_select->get() . '</td>';
        }

        $echo .= '<tr class="mark">
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-article"></i></td>
                    <td class="rex-table-id" data-title="' . rex_i18n::msg('header_id') . '">-</td>
                    <td data-title="' . rex_i18n::msg('header_article_name') . '"><input class="form-control" type="text" name="article-name" autofocus /></td>
                    ' . $tmpl_td . '
                    <td data-title="' . rex_i18n::msg('header_date') . '">' . rex_formatter::strftime(time(), 'date') . '</td>
                    <td class="rex-table-priority" data-title="' . rex_i18n::msg('header_priority') . '"><input class="form-control" type="text" name="article-position" value="' . ($artPager->getRowCount() + 1) . '" /></td>
                    <td class="rex-table-action" colspan="3">'.rex_api_article_add::getHiddenFields().'<button class="btn btn-save" type="submit" name="artadd_function"' . rex::getAccesskey(rex_i18n::msg('article_add'), 'save') . '>' . rex_i18n::msg('article_add') . '</button></td>
                </tr>
                            ';
    }

    // --------------------- ARTIKEL LIST

    for ($i = 0; $i < $sql->getRows(); ++$i) {
        if ($sql->getValue('id') == rex_article::getSiteStartArticleId()) {
            $class = ' rex-icon-sitestartarticle';
        } elseif ($sql->getValue('startarticle') == 1) {
            $class = ' rex-icon-startarticle';
        } else {
            $class = ' rex-icon-article';
        }

        $class_startarticle = '';
        if ($sql->getValue('startarticle') == 1) {
            $class_startarticle = ' rex-startarticle';
        }

        // --------------------- ARTIKEL EDIT FORM

        if ($structure_data->getFunction() == 'edit_art' && $sql->getValue('id') == $structure_data->getArticleId() && $structure_data->getCatPerm()) {
            $tmpl_td = '';
            if ($withTemplates) {
                $template_select->setSelected($sql->getValue('template_id'));
                $tmpl_td = '<td data-title="' . rex_i18n::msg('header_template') . '">' . $template_select->get() . '</td>';
            }
            $echo .= '<tr class="mark' . $class_startarticle . '">
                            <td class="rex-table-icon"><a href="' . $structure_data->getContext()->getUrl(['page' => 'content/edit', 'article_id' => $sql->getValue('id')]) . '" title="' . htmlspecialchars($sql->getValue('name')) . '"><i class="rex-icon' . $class . '"></i></a></td>
                            <td class="rex-table-id" data-title="' . rex_i18n::msg('header_id') . '">' . $sql->getValue('id') . '</td>
                            <td data-title="' . rex_i18n::msg('header_article_name') . '"><input class="form-control" type="text" name="article-name" value="' . htmlspecialchars($sql->getValue('name')) . '" autofocus /></td>
                            ' . $tmpl_td . '
                            <td data-title="' . rex_i18n::msg('header_date') . '">' . rex_formatter::strftime($sql->getDateTimeValue('createdate'), 'date') . '</td>
                            <td class="rex-table-priority" data-title="' . rex_i18n::msg('header_priority') . '"><input class="form-control" type="text" name="article-position" value="' . htmlspecialchars($sql->getValue('priority')) . '" /></td>
                            <td class="rex-table-action" colspan="3">'.rex_api_article_edit::getHiddenFields().'<button class="btn btn-save" type="submit" name="artedit_function"' . rex::getAccesskey(rex_i18n::msg('article_save'), 'save') . '>' . rex_i18n::msg('article_save') . '</button></td>
                        </tr>';
        } elseif ($structure_data->getCatPerm()) {
            // --------------------- ARTIKEL NORMAL VIEW | EDIT AND ENTER

            $article_status = $artStatusTypes[$sql->getValue('status')][0];
            $article_class = $artStatusTypes[$sql->getValue('status')][1];
            $article_icon = $artStatusTypes[$sql->getValue('status')][2];

            $add_extra = '';
            if ($sql->getValue('startarticle') == 1) {
                $add_extra = '<td class="rex-table-action"><span class="text-muted"><i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('delete') . '</span></td>
                              <td class="rex-table-action"><span class="' . $article_class . ' text-muted"><i class="rex-icon ' . $article_icon . '"></i> ' . $article_status . '</span></td>';
            } else {
                if ($structure_data->getCatPerm() && rex::getUser()->hasPerm('publishArticle[]')) {
                    $article_status = '<a class="' . $article_class . '" href="' . $structure_data->getContext()->getUrl(['article_id' => $sql->getValue('id'), 'artstart' => $structure_data->getArtStart()] + rex_api_article_status::getUrlParams()).'"><i class="rex-icon '.$article_icon.'"></i> '.$article_status.'</a>';
                } else {
                    $article_status = '<span class="' . $article_class . ' text-muted"><i class="rex-icon ' . $article_icon . '"></i> ' . $article_status . '</span>';
                }

                $article_delete = '<a href="' . $structure_data->getContext()->getUrl(['article_id' => $sql->getValue('id'), 'artstart' => $structure_data->getArtStart()] + rex_api_article_delete::getUrlParams()).'" data-confirm="'.rex_i18n::msg('delete').' ?"><i class="rex-icon rex-icon-delete"></i> '.rex_i18n::msg('delete').'</a>';

                $add_extra = '<td class="rex-table-action">' . $article_delete . '</td>
                              <td class="rex-table-action">' . $article_status . '</td>';
            }

            $editModeUrl = $structure_data->getContext()->getUrl(['page' => 'content/edit', 'article_id' => $sql->getValue('id'), 'mode' => 'edit']);

            $tmpl_td = '';
            if ($withTemplates) {
                $tmpl = isset($TEMPLATE_NAME[$sql->getValue('template_id')]) ? $TEMPLATE_NAME[$sql->getValue('template_id')] : '';
                $tmpl_td = '<td data-title="' . rex_i18n::msg('header_template') . '">' . $tmpl . '</td>';
            }

            $echo .= '<tr' . (($class_startarticle != '') ? ' class="' . trim($class_startarticle) . '"' : '') . '>
                            <td class="rex-table-icon"><a href="' . $editModeUrl . '" title="' . htmlspecialchars($sql->getValue('name')) . '"><i class="rex-icon' . $class . '"></i></a></td>
                            <td class="rex-table-id" data-title="' . rex_i18n::msg('header_id') . '">' . $sql->getValue('id') . '</td>
                            <td data-title="' . rex_i18n::msg('header_article_name') . '"><a href="' . $editModeUrl . '">' . htmlspecialchars($sql->getValue('name')) . '</a></td>
                            ' . $tmpl_td . '
                            <td data-title="' . rex_i18n::msg('header_date') . '">' . rex_formatter::strftime($sql->getDateTimeValue('createdate'), 'date') . '</td>
                            <td class="rex-table-priority" data-title="' . rex_i18n::msg('header_priority') . '">' . htmlspecialchars($sql->getValue('priority')) . '</td>
                            <td class="rex-table-action"><a href="' . $structure_data->getContext()->getUrl(['article_id' => $sql->getValue('id'), 'function' => 'edit_art', 'artstart' => $structure_data->getArtStart()]).'"><i class="rex-icon rex-icon-edit"></i> '.rex_i18n::msg('change').'</a></td>
                            ' . $add_extra . '
                        </tr>
                        ';
        } else {
            // --------------------- ARTIKEL NORMAL VIEW | NO EDIT NO ENTER

            $art_status = $artStatusTypes[$sql->getValue('status')][0];
            $art_status_class = $artStatusTypes[$sql->getValue('status')][1];
            $art_status_icon = $artStatusTypes[$sql->getValue('status')][2];

            $tmpl_td = '';
            if ($withTemplates) {
                $tmpl = isset($TEMPLATE_NAME[$sql->getValue('template_id')]) ? $TEMPLATE_NAME[$sql->getValue('template_id')] : '';
                $tmpl_td = '<td data-title="' . rex_i18n::msg('header_template') . '">' . $tmpl . '</td>';
            }

            $echo .= '<tr>
                            <td class="rex-table-icon"><i class="rex-icon' . $class . '"></i></td>
                            <td class="rex-table-id" data-title="' . rex_i18n::msg('header_id') . '">' . $sql->getValue('id') . '</td>
                            <td data-title="' . rex_i18n::msg('header_article_name') . '">' . htmlspecialchars($sql->getValue('name')) . '</td>
                            ' . $tmpl_td . '
                            <td data-title="' . rex_i18n::msg('header_date') . '">' . rex_formatter::strftime($sql->getDateTimeValue('createdate'), 'date') . '</td>
                            <td class="rex-table-priority" data-title="' . rex_i18n::msg('header_priority') . '">' . htmlspecialchars($sql->getValue('priority')) . '</td>
                            <td class="rex-table-action"><span class="text-muted"><i class="rex-icon rex-icon-edit"></i> ' . rex_i18n::msg('change') . '</span></td>
                            <td class="rex-table-action"><span class="text-muted"><i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('delete') . '</span></td>
                            <td class="rex-table-action"><span class="' . $art_status_class . ' text-muted"><i class="rex-icon ' . $art_status_icon . '"></i> ' . $art_status . '</span></td>
                        </tr>';
        }

        $sql->next();
    }

    $fragment = new rex_fragment();
    $fragment->setVar('structure_data', $structure_data, false);
    $fragment->setVar('tmpl_head', $tmpl_head, false);
    $fragment->setVar('content', $echo, false);
    $echo = $fragment->parse('structure/table_articles.php');
}

$heading = rex_i18n::msg('structure_articles_caption', $cat_name);
if ($structure_data->getCategoryId() == 0) {
    $heading = rex_i18n::msg('structure_root_level_articles_caption');
}
$fragment = new rex_fragment();
$fragment->setVar('heading', $heading, false);
$fragment->setVar('content', $echo, false);
echo $fragment->parse('core/page/section.php');
