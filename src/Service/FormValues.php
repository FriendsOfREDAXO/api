<?php

namespace FriendsOfRedaxo\Api\Service;

use BackedEnum;
use Closure;
use Redaxo\Core\Form\Field\ArticlePicker;
use Redaxo\Core\Form\Field\Checkbox;
use Redaxo\Core\Form\Field\Choice;
use Redaxo\Core\Form\Field\Collection;
use Redaxo\Core\Form\Field\MediaPicker;
use Redaxo\Core\Form\Field\Number;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\FieldDefinition;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\FormSchema;
use Redaxo\Core\Form\InvalidValueException;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Renderable;
use ReflectionClass;

use function array_key_exists;
use function in_array;
use function is_string;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Writes API input into objects whose fields are declared with the core form attributes (modules, meta schemas).
 *
 * The API exchanges field values in their stored representation, keyed by storage key (the property name in
 * snake_case, or the `key` of the field). Written values run through the core form like a backend submit, so
 * required fields, choices and pickers are checked the same way.
 */
final class FormValues
{
    /**
     * Writes the given stored values into the data object and validates all its fields; fields missing in `$input`
     * keep their current value.
     *
     * @param array<array-key, mixed> $input stored values, keyed by storage key
     * @param Closure(): Form $createForm creates the form of the data object
     * @param list<string>|null $only the properties that may be written, `null` for all
     * @return array<string, string> errors keyed by storage key (`''` for the whole object), empty if valid
     */
    public static function apply(object $data, array $input, Closure $createForm, ?array $only = null): array
    {
        $schema = FormSchema::of($data::class);
        $byKey = [];
        foreach ($schema->fields as $name => $definition) {
            if (null === $only || in_array($name, $only, true)) {
                $byKey[$definition->key] = $definition;
            }
        }

        $errors = [];
        foreach ($input as $key => $_) {
            if (!isset($byKey[(string) $key])) {
                $errors[(string) $key] = 'Unknown field';
            }
        }

        $raw = [];
        foreach ($schema->fields as $name => $definition) {
            $value = $definition->getValue($data);

            if (isset($byKey[$definition->key]) && array_key_exists($definition->key, $input)) {
                try {
                    $value = $definition->field->decode($input[$definition->key], $definition->type);
                } catch (InvalidValueException $e) {
                    $errors[$definition->key] = self::text($e->userMessage);
                    continue;
                }
            }

            try {
                $raw[$name] = $definition->field->toRaw($value);
            } catch (InvalidValueException $e) {
                $errors[$definition->key] = self::text($e->userMessage);
            }
        }

        if ($errors) {
            return $errors;
        }

        $form = $createForm();
        if (!$form->submit($raw)) {
            foreach ($form->getFieldErrors() as $name => $error) {
                $errors[$schema->get($name)->key] = self::text($error);
            }
            foreach ($form->getFormErrors() as $error) {
                $errors[''] = self::text($error);
            }
        }

        return $errors;
    }

    /**
     * The values of the data object in their stored representation, keyed by storage key.
     *
     * @param list<string>|null $only
     * @return array<string, mixed>
     */
    public static function read(object $data, ?array $only = null): array
    {
        $values = [];
        foreach (FormSchema::of($data::class)->fields as $name => $definition) {
            if (null === $only || in_array($name, $only, true)) {
                $values[$definition->key] = $definition->field->encode($definition->getValue($data));
            }
        }

        return $values;
    }

    /**
     * Describes the fields of a class for API clients.
     *
     * @param class-string $class
     * @return list<array<string, mixed>>
     */
    public static function describeFields(string $class): array
    {
        $fields = [];
        foreach (FormSchema::of($class)->fields as $definition) {
            $fields[] = self::describe($definition);
        }

        return $fields;
    }

    /** @return array<string, mixed> */
    public static function describe(FieldDefinition $definition): array
    {
        $field = $definition->field;

        $description = [
            'key' => $definition->key,
            'type' => new ReflectionClass($field)->getShortName(),
            'value_type' => ($definition->type->nullable ? '?' : '') . $definition->type->name,
            'label' => self::text($field->label),
            'required' => $field->required,
        ];

        if (null !== $field->note) {
            $description['note'] = self::text($field->note);
        }

        if ($field instanceof Choice) {
            $description['multiple'] = $field->multiple;
            $choices = [];
            foreach ($field->choices() as $value => $label) {
                $choices[] = ['value' => $value, 'label' => is_string($label) || $label instanceof Renderable ? self::text($label) : $label];
            }
            $description['choices'] = $choices;
        } elseif ($field instanceof MediaPicker || $field instanceof ArticlePicker) {
            $description['multiple'] = $field->multiple;
            $description['stored_as'] = $field instanceof MediaPicker
                ? ($field->multiple ? 'list of media file names' : 'media file name')
                : ($field->multiple ? 'list of article ids' : 'article id');
        } elseif ($field instanceof Number) {
            $description += array_filter(['min' => $field->min, 'max' => $field->max, 'step' => $field->step], static fn ($v) => null !== $v);
        } elseif ($field instanceof Text && null !== $field->maxLength) {
            $description['max_length'] = $field->maxLength;
        } elseif ($field instanceof Checkbox) {
            $description['stored_as'] = 'boolean';
        } elseif ($field instanceof Collection) {
            $description += array_filter(['min' => $field->min, 'max' => $field->max], static fn ($v) => null !== $v);
            $description['stored_as'] = 'list of objects';
            $description['item_fields'] = self::describeFields($field->item);
        }

        return $description;
    }

    /** Plain text of a label, note or message, for JSON. */
    public static function text(mixed $text): string
    {
        return match (true) {
            $text instanceof Translatable => $text->translate(),
            $text instanceof Renderable => html_entity_decode(strip_tags((string) Html::from($text)), ENT_QUOTES | ENT_HTML5),
            $text instanceof BackedEnum => (string) $text->value,
            default => (string) $text,
        };
    }
}
