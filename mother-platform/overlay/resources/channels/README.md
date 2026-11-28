# Sales Channel storefronts

One Main Admin and one shared database serve every channel. Each channel's storefront lives in its OWN folder:

- `resources/channels/<slug>/views/store/*.blade.php` pages, layout, theme and components of that channel only
- `public/channels/<slug>/css|js|img|logo/`            styles, scripts, logo and images of that channel only

The channel is chosen by the visiting domain (Main Admin > Sales Channels). A view that exists in the channel folder is used;
a view that does not exist falls back to the default DutyFreeBD storefront. Nothing is shared between channel folders, so
changing ChildCity never affects Duty Free Dubai or DutyFreeBD. Link a channel asset with `ChannelScope::asset('css/theme.css')`.
Products, stock and orders always come from the shared database and the Main Admin.
