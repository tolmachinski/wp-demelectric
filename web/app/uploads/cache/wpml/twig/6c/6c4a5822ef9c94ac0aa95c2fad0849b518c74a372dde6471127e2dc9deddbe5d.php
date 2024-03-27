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

/* /setup/notice.twig */
class __TwigTemplate_4870e94497ea135771618cf0c33027e4f48e54c2157f8b79eedb919c950ccf7b extends \WPML\Core\Twig\Template
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
        echo "<div id=\"wcml-setup-wizard\" class=\"updated message wpml-message\">
    <p>
        <strong>";
        // line 3
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["text"] ?? null), "prepare", []), "html", null, true);
        echo "</strong><br/>
        ";
        // line 4
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["text"] ?? null), "help", []), "html", null, true);
        echo "
    <ul class=\"wcml-notice-list\">
        <li>";
        // line 6
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["text"] ?? null), "store", []), "html", null, true);
        echo "</li>
        <li>";
        // line 7
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["text"] ?? null), "attributes", []), "html", null, true);
        echo "</li>
        <li>";
        // line 8
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["text"] ?? null), "currencies", []), "html", null, true);
        echo "</li>
    </ul>
    </p>
    <p class=\"submit\">
        <a href=\"";
        // line 12
        echo \WPML\Core\twig_escape_filter($this->env, ($context["setup_url"] ?? null), "html", null, true);
        echo "\"
           class=\"button-primary\">";
        // line 13
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["text"] ?? null), "start", []), "html", null, true);
        echo "</a>
        <a href=\"";
        // line 14
        echo ($context["skip_url"] ?? null);
        echo "\"
           class=\"button-secondary skip\">";
        // line 15
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["text"] ?? null), "skip", []), "html", null, true);
        echo "</a>
    </p>
</div>
";
    }

    public function getTemplateName()
    {
        return "/setup/notice.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  72 => 15,  68 => 14,  64 => 13,  60 => 12,  53 => 8,  49 => 7,  45 => 6,  40 => 4,  36 => 3,  32 => 1,);
    }

    /** @deprecated since 1.27 (to be removed in 2.0). Use getSourceContext() instead */
    public function getSource()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 1.27 and will be removed in 2.0. Use getSourceContext() instead.', E_USER_DEPRECATED);

        return $this->getSourceContext()->getCode();
    }

    public function getSourceContext()
    {
        return new Source("<div id=\"wcml-setup-wizard\" class=\"updated message wpml-message\">
    <p>
        <strong>{{ text.prepare }}</strong><br/>
        {{ text.help }}
    <ul class=\"wcml-notice-list\">
        <li>{{ text.store }}</li>
        <li>{{ text.attributes }}</li>
        <li>{{ text.currencies }}</li>
    </ul>
    </p>
    <p class=\"submit\">
        <a href=\"{{ setup_url }}\"
           class=\"button-primary\">{{ text.start }}</a>
        <a href=\"{{ skip_url|raw }}\"
           class=\"button-secondary skip\">{{ text.skip }}</a>
    </p>
</div>
", "/setup/notice.twig", "/var/www/vhosts/h175360.web45.servicehoster.ch/dev.demelectric.ch/release/web/app/plugins/woocommerce-multilingual/templates/setup/notice.twig");
    }
}
