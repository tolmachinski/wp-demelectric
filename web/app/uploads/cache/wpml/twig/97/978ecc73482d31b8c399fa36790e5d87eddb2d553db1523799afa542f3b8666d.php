<?php

namespace WPML\Core;

use \WPML\Core\Twig\Environment;
use \WPML\Core\Twig\Error\LoaderError;
use \WPML\Core\Twig\Error\RuntimeError;
use \WPML\Core\Twig\Markup;
use \WPML\Core\Twig\Sandbox\SecurityError;
use \WPML\Core\Twig\Sandbox\SecurityNotAllowedTagError;
use \WPML\Core\Twig\Sandbox\SecurityNotAllowedFilterError;
use \WPML\Core\Twig\Sandbox\SecurityNotAllowedFunctionError;
use \WPML\Core\Twig\Source;
use \WPML\Core\Twig\Template;

/* /setup/introduction.twig */
class __TwigTemplate_29e860afad0b614670a7dde404a77f5117cb0d13fc575fd56ce1ecb4635f05c7 extends \WPML\Core\Twig\Template
{
    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        // line 1
        echo "
<span id=\"";
        // line 2
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["strings"] ?? null), "step_id", []), "html", null, true);
        echo "\">
<h1>";
        // line 3
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["strings"] ?? null), "heading", []), "html", null, true);
        echo "</h1>

<p>";
        // line 5
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["strings"] ?? null), "description1", []), "html", null, true);
        echo "</p>
<div>";
        // line 6
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($this->getAttribute(($context["strings"] ?? null), "description2", []), "title", []), "html", null, true);
        echo "</div>
<ul>
    <li>";
        // line 8
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($this->getAttribute(($context["strings"] ?? null), "description2", []), "step1", []), "html", null, true);
        echo "</li>
    <li>";
        // line 9
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($this->getAttribute(($context["strings"] ?? null), "description2", []), "step2", []), "html", null, true);
        echo "</li>
    <li>";
        // line 10
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($this->getAttribute(($context["strings"] ?? null), "description2", []), "step3", []), "html", null, true);
        echo "</li>
</ul>
<p>";
        // line 12
        echo $this->getAttribute(($context["strings"] ?? null), "description3", []);
        echo "</p>

<p class=\"wcml-setup-actions step\">
    <a href=\"";
        // line 15
        echo \WPML\Core\twig_escape_filter($this->env, ($context["later_url"] ?? null), "html", null, true);
        echo "\" class=\"setup_later\">";
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["strings"] ?? null), "later", []), "html", null, true);
        echo "</a>
    <a href=\"";
        // line 16
        echo \WPML\Core\twig_escape_filter($this->env, ($context["continue_url"] ?? null), "html", null, true);
        echo "\" class=\"button button-primary button-large\">";
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["strings"] ?? null), "continue", []), "html", null, true);
        echo "</a>
</p>
</span>
";
    }

    public function getTemplateName()
    {
        return "/setup/introduction.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  78 => 16,  72 => 15,  66 => 12,  61 => 10,  57 => 9,  53 => 8,  48 => 6,  44 => 5,  39 => 3,  35 => 2,  32 => 1,);
    }

    /** @deprecated since 1.27 (to be removed in 2.0). Use getSourceContext() instead */
    public function getSource()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 1.27 and will be removed in 2.0. Use getSourceContext() instead.', E_USER_DEPRECATED);

        return $this->getSourceContext()->getCode();
    }

    public function getSourceContext()
    {
        return new Source("
<span id=\"{{ strings.step_id }}\">
<h1>{{ strings.heading }}</h1>

<p>{{ strings.description1 }}</p>
<div>{{ strings.description2.title }}</div>
<ul>
    <li>{{ strings.description2.step1 }}</li>
    <li>{{ strings.description2.step2 }}</li>
    <li>{{ strings.description2.step3 }}</li>
</ul>
<p>{{ strings.description3|raw }}</p>

<p class=\"wcml-setup-actions step\">
    <a href=\"{{ later_url }}\" class=\"setup_later\">{{ strings.later }}</a>
    <a href=\"{{ continue_url }}\" class=\"button button-primary button-large\">{{ strings.continue }}</a>
</p>
</span>
", "/setup/introduction.twig", "/var/www/vhosts/h175360.web45.servicehoster.ch/dev.demelectric.ch/release/web/app/plugins/woocommerce-multilingual/templates/setup/introduction.twig");
    }
}
