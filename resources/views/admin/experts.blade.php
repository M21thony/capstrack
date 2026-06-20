<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Expert Management - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
</head>
<body>

@include('admin._sidebar')

<div class="main">
  <div class="topbar">
    <div class="page-title">Expert Management</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>

<div class="content">
    <div class="row" style="margin-bottom:16px;gap:8px">
      <input class="input" placeholder="Search experts by name or expertise..." style="max-width:300px"/>
      <select class="input" style="width:160px"><option>All Roles</option><option>Adviser</option><option>Consultant</option><option>Grammarian</option></select>
      <select class="input" style="width:160px"><option>All Status</option><option>Active</option><option>Full</option><option>Inactive</option></select>
      <span style="flex:1"></span>
      <span style="font-size:12px;color:var(--text3)">{{ $experts->count() }} registered experts</span>
    </div>
    <div class="card" style="padding:0;overflow:hidden">
      <table class="table">
        <thead>
          <tr>
            <th style="padding-left:20px">Expert</th>
            <th>Role(s)</th>
            <th>Expertise</th>
            <th>Teams</th>
            <th>Slots Left</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach($experts as $expert)
          <tr>
            <td style="padding-left:20px">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="expert-av" style="background:linear-gradient(135deg,#c9960c,#8a6508);width:32px;height:32px;border-radius:8px;font-size:11px">
                  {{ $expert->avatarInitials() }}
                </div>
                <div>
                  <div class="td-main">{{ $expert->name }}</div>
                  <div style="font-size:11px;color:var(--text3)">{{ $expert->email }}</div>
                </div>
              </div>
            </td>
            <td>
              @if ($expert->is_admin)
                <span class="badge badge-red" style="font-size:10px; background-color: #dc2626; color: #fff;">Admin / Subject Adviser</span>
              @else
                @forelse ($expert->roles as $role)
                  <span class="badge badge-gold" style="font-size:10px; margin-right: 4px;">{{ $role->role_name }}</span>
                @empty
                  <span class="badge badge-gray" style="font-size:10px">No Role</span>
                @endforelse
              @endif
            </td>
            <td style="font-size:12px">
              {{ $expert->expertises->pluck('expertise_field')->join(', ') ?: 'None specified' }}
            </td>
            <td style="font-family:'Space Mono',monospace;font-size:12px">0 / {{ $expert->max_teams ?? 4 }}</td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="pbar" style="width:60px"><div class="pbar-fill pf-green" style="width:0%"></div></div>
                <span style="font-size:11px;color:var(--text3)">{{ $expert->max_teams ?? 4 }}</span>
              </div>
            </td>
            <td><span class="badge badge-green">Active</span></td>
            <td>
              @if ($expert->is_admin)
                <span style="font-size:11px;color:var(--text3);font-weight:600;">Current Admin</span>
              @else
                <button class="btn btn-warning btn-transfer" data-id="{{ $expert->expert_id }}" data-name="{{ $expert->name }}" style="font-size:10px;padding:4px 10px;background:#b5941f;color:#fff;border:none;border-radius:4px;cursor:pointer">Transfer Rights</button>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.btn-transfer').forEach(btn => {
    btn.addEventListener('click', function() {
        const expertId = this.getAttribute('data-id');
        const expertName = this.getAttribute('data-name');
        
        if (confirm(`Are you absolutely sure you want to transfer Admin Rights to ${expertName}?\n\nThis will demote your account to a regular instructor and log you out immediately.`)) {
            fetch("{{ route('admin.turnover') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Accept": "application/json"
                },
                body: JSON.stringify({ expert_id: expertId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = data.redirect_url;
                } else {
                    alert(data.message || 'An error occurred during transfer.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('A network error occurred.');
            });
        }
    });
});
</script>

</body>
</html>
