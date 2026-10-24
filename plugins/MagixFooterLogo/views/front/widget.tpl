{* Fichier : plugins/MagixFooterLogo/views/front/widget.tpl *}
<div class="widget-footerlogo mb-4">
    {if !empty($logoFooter)}
        <a href="{$base_url}" class="d-inline-block mb-4">
            {include file="components/img.tpl"
                img=$logoFooter.img
                size="medium"
                responsiveC=true
                alt=$logoFooter.alt_logo|default:$companyData.name
                title=$logoFooter.title_logo|default:''
                lazy=true
                html_sizes="220px"
            }
        </a>
    {/if}

    {if !empty($companyData.name)}
        <p class="text-white-50 small mb-0 pe-lg-4">
            <span data-translation-key="footer_logo_welcome">{#footer_logo_welcome#}</span> <strong>{$companyData.name}</strong>.
            <span data-translation-key="footer_logo_description">{#footer_logo_description#}</span>
        </p>
    {/if}
</div>
