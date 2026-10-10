<?php

declare(strict_types=1);

namespace App\Service\Project;

/**
 * Minimal, well-commented starting points for new templates.
 */
final class Starters
{
    public const CSS = <<<'CSS'
        /* A4 page: keep the page margin at 0 and pad the content instead */
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: "Helvetica Neue", Arial, sans-serif; font-size: 9.6pt; line-height: 1.42; color: #1f2933; }

        .cv { padding: 16mm 17mm 14mm; }
        .head { display: flex; gap: 6mm; align-items: center; border-bottom: 1.5pt solid #2563eb; padding-bottom: 5mm; margin-bottom: 6mm; }
        .photo { width: 24mm; height: 24mm; border-radius: 50%; object-fit: cover; }
        h1 { font-size: 22pt; letter-spacing: -.01em; }
        .headline { color: #2563eb; font-size: 11pt; margin-top: 1mm; }
        .contact { color: #52606d; font-size: 8.8pt; margin-top: 1.5mm; }

        h2 { font-size: 10.5pt; text-transform: uppercase; letter-spacing: .1em; color: #2563eb; margin: 0 0 2.5mm; break-after: avoid; }
        .block { margin-bottom: 5.5mm; }
        .entry { margin-bottom: 3.5mm; }
        .entry-head { display: flex; justify-content: space-between; gap: 4mm; break-after: avoid; }
        .entry-head h3 { font-size: 10.3pt; }
        .date { color: #7b8794; font-size: 8.8pt; white-space: nowrap; }
        .org { color: #52606d; font-style: italic; }
        ul { padding-left: 4.5mm; margin-top: 1mm; }
        li { margin-bottom: .8mm; break-inside: avoid; }
        .muted { color: #7b8794; }
        CSS;

    public const TWIG = <<<'TWIG'
        {# Starter template (Twig). Variables: header, regions.<name>, sections, labels, cv. See Docs. #}
        <div class="cv">
          <header class="head">
            {% if header.photo %}<img class="photo" src="{{ header.photo }}" alt="">{% endif %}
            <div>
              <h1>{{ header.name }}</h1>
              {% if header.headline %}<p class="headline">{{ header.headline }}</p>{% endif %}
              <p class="contact">{{ [header.location, header.phone, header.email, header.linkedin|pretty_url, header.github|pretty_url]|filter(v => v)|join('  ·  ') }}</p>
            </div>
          </header>

          {% for s in regions.main %}
            <section class="block">
              <h2>{{ s.title }}</h2>
              {% if s.type == 'summary' %}
                <p>{{ s.text|md }}</p>
              {% elseif s.type == 'skills' %}
                {% for item in s.items %}<p><strong>{{ item.group }}:</strong> {{ item.skills }}</p>{% endfor %}
              {% elseif s.type == 'languages' %}
                <p>{% for item in s.items %}<strong>{{ item.name }}</strong> {{ item.level }}{% if not loop.last %} · {% endif %}{% endfor %}</p>
              {% else %}
                {% for item in s.items %}
                  <div class="entry">
                    <div class="entry-head">
                      <h3>{{ item.heading|md }}</h3>
                      <span class="date">{{ item.period }}</span>
                    </div>
                    {% if item.subheading %}<p class="org">{{ item.subheading }}</p>{% endif %}
                    {% if item.description %}<p>{{ item.description|md }}</p>{% endif %}
                    {% if item.bullets is defined and item.bullets %}<ul>{% for b in item.bullets %}<li>{{ b|md }}</li>{% endfor %}</ul>{% endif %}
                  </div>
                {% endfor %}
              {% endif %}
            </section>
          {% endfor %}
        </div>
        TWIG;

    public const MUSTACHE = <<<'HTML'
        <!-- Starter template (CVolve HTML / Mustache). {{x}} = text, {{{x}}} = formatted text, {{#x}}…{{/x}} = loop or condition. -->
        <div class="cv">
          <header class="head">
            {{#header.photo}}<img class="photo" src="{{header.photo}}" alt="">{{/header.photo}}
            <div>
              <h1>{{header.name}}</h1>
              {{#header.headline}}<p class="headline">{{header.headline}}</p>{{/header.headline}}
              <p class="contact">{{header.location}} · {{header.phone}} · {{header.email}}</p>
            </div>
          </header>

          {{#regions.main}}
            <section class="block">
              <h2>{{title}}</h2>
              {{#is_summary}}<p>{{{text}}}</p>{{/is_summary}}
              {{#is_skills}}{{#items}}<p><strong>{{group}}:</strong> {{skills}}</p>{{/items}}{{/is_skills}}
              {{^is_summary}}{{^is_skills}}
                {{#items}}
                  <div class="entry">
                    <div class="entry-head">
                      <h3>{{{heading}}}</h3>
                      <span class="date">{{period}}</span>
                    </div>
                    {{#subheading}}<p class="org">{{subheading}}</p>{{/subheading}}
                    {{#description}}<p>{{{description}}}</p>{{/description}}
                    {{#bullets.0}}<ul>{{#bullets}}<li>{{{.}}}</li>{{/bullets}}</ul>{{/bullets.0}}
                  </div>
                {{/items}}
              {{/is_skills}}{{/is_summary}}
            </section>
          {{/regions.main}}
        </div>
        HTML;

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $sections = [];
        foreach (['summary', 'experience', 'projects', 'education', 'skills', 'languages', 'certifications', 'publications', 'custom'] as $type) {
            $sections[$type] = ['region' => 'main'];
        }
        $sections['summary']['maxLength'] = 900;

        return ['regions' => ['main'], 'sections' => $sections];
    }
}
