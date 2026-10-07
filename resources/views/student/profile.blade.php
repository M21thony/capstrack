<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content"/>
<title>My Profile - CapsTrack Student Portal</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="styles.css"/>
  @include('student.partials.render-whole')
</head>
<body>

@include('student.partials.sidebar')

<div class="main">
  <div class="topbar">
    <div class="page-title">Dashboard</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    <div style="color:var(--text3);font-size:14px;padding:24px 0 32px;text-align:center">
      Click your profile in the upper left to open the profile modal.
    </div>

    @include('partials.notification-settings')
  </div>
</div>

</script>
@include('partials.escape-closes-modals')
</body>
</html>