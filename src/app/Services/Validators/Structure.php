<?php

namespace LaravelEnso\Forms\app\Services\Validators;

use LaravelEnso\Helpers\app\Classes\Obj;
use LaravelEnso\Forms\app\Exceptions\TemplateException;
use LaravelEnso\Forms\app\Attributes\Structure as Attributes;

class Structure
{
    private $template;

    public function __construct(Obj $template)
    {
        $this->template = $template;
    }

    public function validate()
    {
        $this->checkRootMandatoryAttributes()
            ->checkRootOptionalAttributes()
            ->checkRootAttributesFormat()
            ->checkSections()
            ->checkTabs();
    }

    private function checkRootMandatoryAttributes()
    {
        $diff = collect(Attributes::Mandatory)
            ->diff($this->template->keys());

        if ($diff->isNotEmpty()) {
            throw TemplateException::missingRootAttributes($diff->implode('", "'));
        }

        return $this;
    }

    private function checkRootOptionalAttributes()
    {
        $attributes = collect(Attributes::Mandatory)
            ->merge(Attributes::Optional);

        $diff = $this->template->keys()
            ->diff($attributes);

        if ($diff->isNotEmpty()) {
            throw TemplateException::unknownRootAttributes($diff->implode('", "'));
        }

        return $this;
    }

    private function checkRootAttributesFormat()
    {
        if ($this->template->has('actions')
            && ! $this->template->get('actions') instanceof Obj) {
            throw TemplateException::invalidActionsFormat();
        }

        if ($this->template->has('params')
            && ! $this->template->get('params') instanceof Obj) {
            throw TemplateException::invalidParamsFormat();
        }

        if (! $this->template->get('sections') instanceof Obj) {
            throw TemplateException::invalidSectionFormat();
        }

        return $this;
    }

    private function checkSections()
    {
        $attributes = $this->template->get('sections')
            ->reduce(function ($attributes, $section) {
                return $attributes->merge($section->keys());
            }, collect())->unique()->values();

        $this->checkSectionsMandatory($attributes)
            ->checkSectionsOptional($attributes)
            ->checkColumnsFormat();

        return $this;
    }

    private function checkSectionsMandatory($attributes)
    {
        $diff = collect(Attributes::SectionMandatory)
            ->diff($attributes);

        if ($diff->isNotEmpty()) {
            throw TemplateException::missingSectionAttributes($diff->implode('", "'));
        }

        return $this;
    }

    private function checkSectionsOptional($attributes)
    {
        $diff = $attributes->diff(
            collect(Attributes::SectionMandatory)
                ->merge(Attributes::SectionOptional)
        );

        if ($diff->isNotEmpty()) {
            throw TemplateException::unknownSectionAttributes($diff->implode('", "'));
        }

        return $this;
    }

    private function checkColumnsFormat()
    {
        $this->template->get('sections')
            ->each(function ($section) {
                if (! collect(Attributes::Columns)->contains($section->get('columns'))) {
                    throw TemplateException::invalidColumnsAttributes(
                        $section->get('columns'),
                        collect(Attributes::Columns)->implode(', ')
                    );
                }

                if ($section->get('columns') === 'custom') {
                    $this->checkCustomColumns($section);
                }
            });
    }

    private function checkCustomColumns($section)
    {
        $section->get('fields')
            ->each(function ($field) {
                if (! $field->has('column')) {
                    throw TemplateException::missingFieldColumn($field->get('name'));
                }

                if (! is_int($field->get('column'))
                    || $field->get('column') <= 0
                    || $field->get('column') > 12) {
                    throw TemplateException::invalidFieldColumn($field->get('name'));
                }
            });
    }

    private function checkTabs()
    {
        if (! $this->template->get('tabs')) {
            return;
        }

        $diff = $this->template->get('sections')
            ->filter(function ($section) {
                return ! $section->has('tab');
            });

        if ($diff->isNotEmpty()) {
            throw TemplateException::missingTabAttribute($diff->keys()->implode('", "'));
        }
    }
}
