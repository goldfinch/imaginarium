<picture<% if Params.pictureClass %> class="$Params.pictureClass"<% end_if %>>
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
    class="lazy<% if Params.imgClass %> $Params.imgClass<% end_if %>"
    data-src="{$FirstImageLink}"
    src="{$PlaceholderImageURL}"
    <% else %>
    src="{$FirstImageLink}"
    <% if Params.imgClass %> class="$Params.imgClass"<% end_if %>
    <% end_if %>
    <% if FocusPoint %>
    style="object-position: {$FocusPoint.PercentageX}% {$FocusPoint.PercentageY}%"
    <% end_if %>
  >
</picture>
