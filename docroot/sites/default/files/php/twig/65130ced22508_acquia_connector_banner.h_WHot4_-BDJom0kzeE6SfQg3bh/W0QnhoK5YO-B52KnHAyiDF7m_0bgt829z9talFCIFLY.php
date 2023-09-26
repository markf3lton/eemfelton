<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* modules/contrib/acquia_connector/templates/acquia_connector_banner.html.twig */
class __TwigTemplate_93d317a42b6a6d28d242485fbf5e782e extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->env->getExtension('\Twig\Extension\SandboxExtension');
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "<div class=\"an-start-form\">
    <div class=\"an-pg-container\">
        <div class=\"an-wrapper\">
            <h2 class=\"an-info-header\">Acquia Subscription</h2>
            <p class=\"an-slogan\">A suite of products and services to create &amp; maintain killer web experiences built on Drupal</p>
            <div class=\"an-info-box\">
                <div class=\"cell with-arrow an-left\">
                    <h2 class=\"cell-title\"><i>Answers you need</i></h2>
                    <a href=\"https://docs.acquia.com\" target=\"_blank\"><img src=\"/";
        // line 9
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["attributes"] ?? null), "path", [], "any", false, false, true, 9), 9, $this->source), "html", null, true);
        echo "/images/icon-library.png\" alt=\"\" typeof=\"Image\"></a>
                    <p class=\"cell-p\">Tap the collective knowledge of Acquia’s technical support team &amp; partners.</p>
                </div>
                <div class=\"cell with-arrow an-center\"><h2 class=\"cell-title\"><i>Tools to extend your site</i></h2>
                    <a href=\"https://www.acquia.com/customer-success\" target=\"_blank\"><img src=\"/";
        // line 13
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["attributes"] ?? null), "path", [], "any", false, false, true, 13), 13, $this->source), "html", null, true);
        echo "/images/icon-tools.png\" alt=\"\" typeof=\"Image\"></a>
                    <p class=\"cell-p\">Enhance and extend your site with an array of <a href=\"https://www.acquia.com/products-services/acquia-cloud\" target=\"_blank\">services</a> from Acquia &amp; our partners.</p>
                </div><div class=\"cell an-right\"><h2 class=\"cell-title\"><i>Support when you want it</i></h2>
                    <a href=\"https://support.acquia.com\" target=\"_blank\"><img src=\"/";
        // line 16
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["attributes"] ?? null), "path", [], "any", false, false, true, 16), 16, $this->source), "html", null, true);
        echo "/images/icon-support.png\" alt=\"\" typeof=\"Image\"></a>
                    <p class=\"cell-p\">Experienced Drupalists are available to support you whenever you need it.</p>
                </div>
            </div>
        </div>
    </div>
</div>
";
        // line 23
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["form"] ?? null), 23, $this->source), "html", null, true);
    }

    public function getTemplateName()
    {
        return "modules/contrib/acquia_connector/templates/acquia_connector_banner.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  72 => 23,  62 => 16,  56 => 13,  49 => 9,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "modules/contrib/acquia_connector/templates/acquia_connector_banner.html.twig", "/home/ide/project/docroot/modules/contrib/acquia_connector/templates/acquia_connector_banner.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = array();
        static $filters = array("escape" => 9);
        static $functions = array();

        try {
            $this->sandbox->checkSecurity(
                [],
                ['escape'],
                []
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
