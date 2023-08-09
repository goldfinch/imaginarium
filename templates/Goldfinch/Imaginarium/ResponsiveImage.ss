<picture>
	<% loop $Sizes %>
	<source
    media="{$MediaQuery}"
    <% if Lazy %>
    data-srcset="{$Image.URL}"
    <% else %>
    srcset="{$Image.URL}"
    <% end_if %>
  >
	<% end_loop %>
  <img
    <% if LazyLoadingTag %>
    loading="lazy"
    <% end_if %>
    <% if Lazy %>
    class="lazy"
    data-src="{$DefaultImage.URL}"
    src="{$DefaultImagePlaceholder.URL}"
    <% else %>
    src="{$DefaultImage.URL}"
    <% end_if %>
    style="transform-origin: {$FocusPoint.PercentageX}% {$FocusPoint.PercentageY}%"
  >
</picture>
