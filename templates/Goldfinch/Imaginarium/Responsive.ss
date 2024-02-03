<picture>
	<% loop $Sizes %>
    <% if ImageAvif %>
      <source
        media="{$MediaQuery}"
        <% if Lazy %>
        data-srcset="{$ImageAvif}"
        <% else %>
        srcset="{$ImageAvif}"
        <% end_if %>
        width="{$Image.Width}"
        height="{$Image.Height}"
        type="image/avif"
      >
    <% end_if %>
    <% if ImageWebp %>
      <source
        media="{$MediaQuery}"
        <% if Lazy %>
        data-srcset="{$ImageWebp}"
        <% else %>
        srcset="{$ImageWebp}"
        <% end_if %>
        width="{$Image.Width}"
        height="{$Image.Height}"
        type="image/webp"
      >
    <% end_if %>
    <source
      media="{$MediaQuery}"
      <% if Lazy %>
      data-srcset="{$Image.URL}"
      <% else %>
      srcset="{$Image.URL}"
      <% end_if %>
      width="{$Image.Width}"
      height="{$Image.Height}"
      type="$Image.MimeType"
    >
	<% end_loop %>
  <img
    alt="{$FirstImage.Title}"
    width="{$FirstImage.Width}"
    height="{$FirstImage.Height}"
    decoding="async"
    <% if LazyLoadingTag %>
    loading="lazy"
    <% end_if %>
    <% if Lazy %>
    class="lazy"
    data-src="{$FirstImage.Link}"
    src="{$PlaceholderImageURL}"
    <% else %>
    src="{$FirstImage.Link}"
    <% end_if %>
    <% if FocusPoint %>
    style="transform-origin: {$FocusPoint.PercentageX}% {$FocusPoint.PercentageY}%"
    <% end_if %>
  >
</picture>
