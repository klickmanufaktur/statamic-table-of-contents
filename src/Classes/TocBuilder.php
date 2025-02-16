<?php

namespace Doefom\StatamicTableOfContents\Classes;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Statamic\Support\Arr;
use Statamic\Support\Str;

class TocBuilder
{
    protected string $html;

    protected int $minLevel = 1;

    protected int $maxLevel = 6;

    protected bool $ordered = false;

    public function __construct(string $html)
    {
        $this->html = $html;
    }

    public function setMinLevel(int $minLevel): self
    {
        $this->minLevel = $minLevel;

        return $this;
    }

    public function setMaxLevel(int $maxLevel): self
    {
        $this->maxLevel = $maxLevel;

        return $this;
    }

    public function setOrdered(bool $ordered): self
    {
        $this->ordered = $ordered;

        return $this;
    }

    /**
     * Build the table of contents as HTML.
     */
    public function getTocMarkup(): string
    {
        $headings = $this->getHeadingsFormatted();

        if ($headings->isEmpty()) {
            return '';
        }

        $ordered    = $this->ordered;
        $startLevel = $headings->pluck('level')->min();
        $listTag    = $ordered ? 'ol' : 'ul';

        $toc       = '';
        // Wir starten eine Ebene unterhalb der tiefsten Überschrift,
        // damit sich der Schleifenmechanismus vereinfacht.
        $prevLevel = $startLevel - 1;

        foreach ($headings as $heading) {
            $currentLevel = Arr::get($heading, 'level');

            if ($currentLevel > $prevLevel) {
                // Bei steigendem Level öffnen wir so viele Listen, wie nötig.
                for ($i = $prevLevel + 1; $i <= $currentLevel; $i++) {
                    $toc .= "<$listTag>";
                }
            } elseif ($currentLevel < $prevLevel) {
                // Bei abfallendem Level schließen wir zuerst das letzte Listenelement
                // und dann so viele verschachtelte Listen, wie nötig.
                for ($i = $prevLevel; $i > $currentLevel; $i--) {
                    $toc .= "</li></$listTag>";
                }
                $toc .= "</li>";
            } else {
                // Gleiche Ebene: Schließe das vorherige LI.
                $toc .= "</li>";
            }

            $slug = Arr::get($heading, 'slug');
            $text = Arr::get($heading, 'text');
            $toc .= '<li><a href="#' . $slug . '">' . $text . '</a>';

            $prevLevel = $currentLevel;
        }

        // Schließe alle noch offenen LI-Elemente und Listen
        for ($i = $prevLevel; $i >= $startLevel; $i--) {
            $toc .= "</li></$listTag>";
        }

        return $toc;
    }

    public function addIdsToHeadings(): string
    {
        $doc = new DOMDocument;
        $doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));

        if (trim($this->html) === '') {
            return '';
        }

        $xpath = new DOMXPath($doc);
        $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');

        $usedSlugs = collect();
        foreach ($headings as $heading) {
            $slug = Str::slug($heading->textContent);
            $suffix = 1;

            while ($usedSlugs->contains($slug)) {
                $slug = Str::slug($heading->textContent).'-'.$suffix;
                $suffix++;
            }

            $usedSlugs->add($slug);

            $heading->setAttribute('id', "$slug");
        }

        return $doc->saveHTML();
    }

    /**
     * Extract all headings from the HTML respecting the min and max level.
     *
     * @return Collection A collection of headings with 'level' and 'text' keys.
     */
    private function getHeadingsFormatted(): Collection
    {
        $minLevel = $this->minLevel;
        $maxLevel = $this->maxLevel;

        if (trim($this->html) === '') {
            return collect();
        }

        $doc = new DOMDocument;
        $doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));

        $xpath = new DOMXPath($doc);
        $range = collect(range($minLevel, $maxLevel));
        $expression = $range->map(fn ($level) => "//h$level")->implode('|'); // e.g. //h2|//h3|//h4

        $headingNodes = $xpath->query($expression);

        // Loop through the matches and extract the heading level and text
        $headings = collect();
        $usedSlugs = collect();
        foreach ($headingNodes as $headingNode) {
            $level = intval($headingNode->nodeName[1]);
            $text = $headingNode->textContent;
            $slug = Str::slug($text);

            // Ensure the slug is unique or this table of contents
            $suffix = 1;
            while ($usedSlugs->contains($slug)) {
                $slug = Str::slug($text).'-'.$suffix;
                $suffix++;
            }

            // Keep track of the used slugs
            $usedSlugs->add($slug);

            // Add the heading to the collection
            $headings->add([
                'level' => $level,
                'text' => $text,
                'slug' => $slug,
            ]);
        }

        return $headings;
    }
}
