<?php

namespace FriendsOfRedaxo\Api\Service;

use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Core;

/**
 * Reads and writes the module fields of slices the way the backend content page does.
 *
 * The API exchanges the field values in their stored representation: a `data` object keyed by storage key (the
 * property name in snake_case), exactly as `rex_article_slice.data` holds it. Written values run through the module
 * form like a backend submit — required fields, choices, pickers and the module's own `validate()` apply, and
 * `beforeSave()` derives values — so the API cannot store anything the backend would reject.
 */
final class SliceService
{
    /** @return array<string, mixed> */
    public static function toArray(ArticleSlice $slice): array
    {
        return [
            'id' => $slice->id,
            'article_id' => $slice->articleId,
            'language_id' => $slice->languageId,
            'content_section_id' => $slice->contentSectionId,
            'module' => $slice->moduleKey,
            'priority' => $slice->priority,
            'status' => $slice->status,
            'revision' => $slice->revision,
            'createdate' => date('Y-m-d H:i:s', $slice->createdate),
            'createuser' => $slice->createuser,
            'updatedate' => date('Y-m-d H:i:s', $slice->updatedate),
            'updateuser' => $slice->updateuser,
            'data' => (object) $slice->data,
        ];
    }

    /**
     * A transient slice for a module instance, e.g. of a slice that is about to be created.
     *
     * `ArticleSlice::forNewSlice()` requires a backend user, which a bearer-token request does not have.
     *
     * @param array<string, mixed> $data stored values
     */
    public static function transientSlice(int $articleId, int $languageId, int $contentSectionId, string $moduleKey, int $revision = 0, array $data = [], int $id = 0): ArticleSlice
    {
        $user = Core::getUser()->login ?? 'API';

        return ArticleSlice::__set_state([
            'id' => $id,
            'articleId' => $articleId,
            'languageId' => $languageId,
            'contentSectionId' => $contentSectionId,
            'moduleKey' => $moduleKey,
            'priority' => 0,
            'status' => 1,
            'createdate' => time(),
            'updatedate' => time(),
            'createuser' => $user,
            'updateuser' => $user,
            'revision' => $revision,
            'data' => $data,
        ]);
    }

    /**
     * Applies the given stored values to the module of the slice and validates all fields like a backend submit.
     *
     * Fields missing in `$input` keep the value of the slice, so this works for complete (POST/PUT) and partial
     * (PATCH) writes alike.
     *
     * @param array<array-key, mixed> $input stored values, keyed by storage key
     * @return array{module: Module, errors: array<string, string>} on success `errors` is empty and the module holds
     *     the values to save (see {@see Module::encodeData()})
     */
    public static function apply(ArticleSlice $slice, array $input): array
    {
        $module = Module::of($slice);

        $errors = FormValues::apply($module, $input, $module->createForm(...));
        if ($errors) {
            return ['module' => $module, 'errors' => $errors];
        }

        $error = $module->validate();
        if (null !== $error) {
            return ['module' => $module, 'errors' => ['' => FormValues::text($error)]];
        }

        $module->beforeSave();

        return ['module' => $module, 'errors' => []];
    }
}
