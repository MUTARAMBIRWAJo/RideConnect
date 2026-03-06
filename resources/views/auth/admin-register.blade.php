<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Access — RideConnect Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --rc-black:       var(--fi-ink);
            --rc-surface:     var(--fi-surface);
            --rc-card:        var(--fi-card-bg);
            --rc-card-border: var(--fi-border-subtle);
            --rc-accent:      var(--color-primary);
            --rc-accent-glow: rgba(22,101,52,0.3);
            --rc-accent-dark: var(--color-primary);
            --rc-text-primary:   var(--fi-light-text);
            --rc-text-secondary: var(--fi-muted-2, #cbd5e1);
            --rc-text-muted:     var(--color-muted, #94a3b8);
            --rc-input-bg:    var(--fi-card-bg);
            --rc-input-border: rgba(255,255,255,0.09);
            --rc-input-focus:  rgba(74,222,128,0.35);
            --rc-error:       var(--color-danger);
            --rc-error-bg:    rgba(239,68,68,0.08);
            --radius-sm:6px; --radius-md:12px; --radius-lg:18px; --radius-xl:24px;
            --shadow-card: 0 0 0 1px var(--rc-card-border), 0 32px 64px rgba(0,0,0,0.55), 0 8px 24px rgba(0,0,0,0.35);
            --shadow-btn:  0 4px 14px rgba(22,101,52,0.35);
            --transition-fast: 0.15s cubic-bezier(0.4,0,0.2,1);
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        html{font-size:16px;-webkit-font-smoothing:antialiased;}
        body{
            font-family:'DM Sans',sans-serif;
            background-color:var(--rc-black);
            color:var(--rc-text-primary);
            min-height:100vh;
            padding:40px 24px;
            position:relative;
            overflow-x:hidden;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        body::before{
            content:'';position:fixed;inset:0;
            background:
                radial-gradient(ellipse 80% 60% at 20% -10%,rgba(22,101,52,0.16) 0%,transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 110%,rgba(16,24,40,0.8) 0%,transparent 60%),
                radial-gradient(ellipse 40% 40% at 50% 50%,rgba(74,222,128,0.06) 0%,transparent 70%);
            pointer-events:none;z-index:0;
        }
        body::after{
            content:'';position:fixed;inset:0;
            background-image:linear-gradient(rgba(255,255,255,0.015) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.015) 1px,transparent 1px);
            background-size:48px 48px;pointer-events:none;z-index:0;
        }

        /* Brand */
        .brand{display:flex;align-items:center;gap:10px;text-decoration:none;position:relative;z-index:1;margin-bottom:32px;}
        .brand-icon{width:38px;height:38px;background:linear-gradient(135deg,var(--rc-accent) 0%,var(--color-primary,#1d4ed8) 100%);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 20px var(--rc-accent-glow);}
        .brand-name{font-family:'Sora',sans-serif;font-size:1.2rem;font-weight:700;color:var(--rc-text-primary);letter-spacing:-0.02em;}
        .brand-name span{color:var(--rc-accent);}

        /* Auth Card */
        .auth-card{background:var(--rc-card);border:1px solid var(--rc-card-border);border-radius:var(--radius-xl);box-shadow:var(--shadow-card);width:100%;max-width:520px;padding:40px 36px;backdrop-filter:blur(12px);position:relative;z-index:1;}

        /* Card Header */
        .card-header{margin-bottom:32px;}
        .card-eyebrow{font-size:0.72rem;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:var(--rc-accent);margin-bottom:10px;}
        .card-title{font-family:'Sora',sans-serif;font-size:1.65rem;font-weight:700;color:var(--rc-text-primary);letter-spacing:-0.03em;line-height:1.2;margin-bottom:8px;}
        .card-subtitle{font-size:0.875rem;color:var(--rc-text-secondary);line-height:1.5;}

        /* Form */
        .form-group{margin-bottom:20px;}
        .form-label{display:block;font-size:0.8rem;font-weight:600;color:var(--rc-text-secondary);margin-bottom:7px;letter-spacing:0.01em;}
        .form-label .required{color:var(--rc-accent);margin-left:2px;}
        .input-wrapper{position:relative;}
        .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--rc-text-muted);pointer-events:none;transition:color var(--transition-fast);}
        .input-icon svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
        .form-control{width:100%;background:var(--rc-input-bg);border:1px solid var(--rc-input-border);border-radius:var(--radius-md);color:var(--rc-text-primary);font-family:'DM Sans',sans-serif;font-size:0.9rem;padding:11px 14px 11px 40px;outline:none;transition:border-color var(--transition-fast),box-shadow var(--transition-fast),background var(--transition-fast);-webkit-appearance:none;}
        select.form-control{cursor:pointer;padding-right:36px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238b95a6' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;}
        .form-control::placeholder{color:var(--rc-text-muted);}
        .form-control:focus{border-color:var(--rc-accent);box-shadow:0 0 0 3px var(--rc-input-focus);background:var(--fi-surface,#1e2840);}
        .input-wrapper:focus-within .input-icon{color:var(--rc-accent);}
        .form-control.is-invalid{border-color:var(--rc-error);box-shadow:0 0 0 3px rgba(248,113,113,0.15);}

        /* Errors */
        .field-error{display:flex;align-items:center;gap:5px;font-size:0.775rem;color:var(--rc-error);margin-top:6px;}
        .field-error svg{width:13px;height:13px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}
        .field-hint{font-size:0.75rem;color:var(--rc-text-muted);margin-top:5px;}
        .alert-error{background:var(--rc-error-bg);border:1px solid rgba(248,113,113,0.2);border-radius:var(--radius-md);padding:12px 16px;font-size:0.845rem;color:var(--rc-error);margin-bottom:24px;display:flex;align-items:flex-start;gap:10px;}
        .alert-error svg{width:16px;height:16px;flex-shrink:0;margin-top:1px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

        /* Row */
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .form-divider{height:1px;background:var(--rc-card-border);margin:24px 0;}
        .section-label{font-size:0.7rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--rc-text-muted);margin-bottom:16px;}

        /* Buttons */
        .btn-primary{width:100%;background:linear-gradient(135deg,var(--rc-accent) 0%,var(--rc-accent-dark) 100%);color:var(--fi-light-text,#fff);font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:600;border:none;border-radius:var(--radius-md);padding:12px 24px;cursor:pointer;box-shadow:var(--shadow-btn);transition:transform var(--transition-fast),box-shadow var(--transition-fast);letter-spacing:0.01em;position:relative;overflow:hidden;margin-top:8px;}
        .btn-primary::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.1) 0%,transparent 60%);opacity:0;transition:opacity var(--transition-fast);}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(22,101,52,0.45);}
        .btn-primary:hover::after{opacity:1;}
        .btn-primary:active{transform:translateY(0);}
        .btn-primary:disabled{opacity:0.6;cursor:not-allowed;}

        /* Links */
        .link{color:var(--rc-text-secondary);text-decoration:none;font-size:0.845rem;font-weight:500;transition:color var(--transition-fast);}
        .link:hover{color:var(--rc-accent);text-decoration:underline;}
        .card-footer-text{text-align:center;margin-top:24px;font-size:0.845rem;color:var(--rc-text-muted);}
        
        /* Page Footer */
        .page-footer{font-size:0.75rem;color:var(--rc-text-muted);display:flex;align-items:center;gap:6px;margin-top:24px;position:relative;z-index:1;}
        .page-footer .dot{width:4px;height:4px;border-radius:50%;background:var(--rc-text-muted);opacity:0.4;}

        @media(max-width:540px){
            .auth-card{padding:28px 20px;}
            .form-row{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>

    <div style="display:flex;flex-direction:column;align-items:center;">
        <a href="{{ url('/') }}" class="brand">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                    <path d="M3 11l2-5h14l2 5M3 11v5h2v2h2v-2h10v2h2v-2h2v-5M3 11h18M7 16h.01M17 16h.01"/>
                </svg>
            </div>
            <span class="brand-name">Ride<span>Connect</span></span>
        </a>
        
        <div class="auth-card">
            <div class="card-header">
                <p class="card-eyebrow">Registration Form</p>
                <h1 class="card-title">Request Access</h1>
                <p class="card-subtitle">Request access to manage your organization's rides.</p>
            </div>
            
            <form method="POST" action="{{ route('admin.register') }}">
                @csrf
                
                @if($errors->any())
                <div class="alert-error">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
                @endif
                
                <p class="section-label">Personal Information</p>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="name">Full name <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   placeholder="Jane Doe" value="{{ old('name') }}" required>
                        </div>
                        @error('name')
                        <p class="field-error">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            {{ $message }}
                        </p>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone number</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.09 1.2 2 2 0 012.07 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                            </span>
                            <input type="tel" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" 
                                   placeholder="+250 788 000 000" value="{{ old('phone') }}">
                        </div>
                        @error('phone')
                        <p class="field-error">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            {{ $message }}
                        </p>
                        @enderror
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email address <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>
                        </span>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                               placeholder="jane@company.com" value="{{ old('email') }}" required>
                    </div>
                    @error('email')
                    <p class="field-error">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        {{ $message }}
                    </p>
                    @enderror
                </div>
                
                <div class="form-divider"></div>
                <p class="section-label">Set Password</p>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">Password <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" 
                                   placeholder="Min. 8 characters" required>
                        </div>
                        <p class="field-hint">Use 8+ chars, a number & symbol.</p>
                        @error('password')
                        <p class="field-error">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            {{ $message }}
                        </p>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password_confirmation">Confirm password <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" 
                                   placeholder="Re-enter password" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">Request Access</button>
            </form>
            
            <p class="card-footer-text">Already have an account? <a href="{{ route('admin.login') }}" class="link">Sign in</a></p>
        </div>
        
        <div class="page-footer">
            <span>&copy; 2025 RideConnect</span><span class="dot"></span>
            <span>Admin Panel</span><span class="dot"></span><span>v2.0</span>
        </div>
    </div>

</body>
</html>
