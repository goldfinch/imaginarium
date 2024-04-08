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
      data-srcset="{$Up.CDNSuffix}{$Image.URL}"
      <% else %>
      srcset="{$Up.CDNSuffix}{$Image.URL}"
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
    <% if DecodingTag %>
    decoding="$DecodingTag"
    <% end_if %>
    <% if LazyLoadingTag %>
      loading="lazy"
    <% end_if %>
    <% if FetchPriorityTag %>
      fetchpriority="$FetchPriorityTag"
    <% end_if %>
    data-loaded="false"
    <% if Lazy %>
    class="lazy"
    data-src="{$FirstImageLink}"
    src="{$PlaceholderImageURL}"
    <% else %>
    src="{$FirstImageLink}"
    <% end_if %>
    <% if FocusPoint %>
    style="transform-origin: {$FocusPoint.PercentageX}% {$FocusPoint.PercentageY}%"
    <% end_if %>
  >
</picture>
