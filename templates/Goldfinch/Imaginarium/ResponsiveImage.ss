<picture>
	<% loop $Sizes %>
	<source
    media="{$MediaQuery}"
    data-srcset="{$Image.URL}"
    data-breakpoint="{$Breakpoint}"
    data-width="{$Image.Width}"
    data-height="{$Image.Height}"
  >
	<% end_loop %>
  <img
    class="lazy"
    alt="{$Title}"
    data-src="{$DefaultImage.URL}"
    src="{$DefaultImagePlaceholder.URL}"
  >
</picture>
