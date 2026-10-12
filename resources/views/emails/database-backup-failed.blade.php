@extends('emails.layouts.master')

@section('title', 'Database Backup Failed')

@section('content')

    <tr>
        <td style="padding:40px;text-align:center;">

            <div style="margin-bottom:24px;">
                <span style="font-size:48px;">⚠️</span>
            </div>

            <p style="margin:0 0 16px 0;font-size:22px;line-height:1.3;font-weight:800;color:#183A41;">
                {{ $appName }} Backup Failed
            </p>

            <p style="margin:0 auto 24px;font-size:15px;line-height:1.6;color:#546875;max-width:440px;">
                The scheduled database backup could not be completed successfully.
                Please check the error details below and review the application logs if necessary.
            </p>

            <table
                role="presentation"
                width="100%"
                cellspacing="0"
                cellpadding="0"
                style="border:1px solid #F1D5D5;border-radius:12px;background-color:#FFF5F5;margin:0 0 24px;"
            >
                <tr>
                    <td style="padding:16px 20px 8px;font-size:15px;color:#4A626A;text-align:left;">
                        <strong style="color:#183A41;">File:</strong>
                        {{ $attachmentName }}
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 20px 8px;font-size:15px;color:#4A626A;text-align:left;">
                        <strong style="color:#183A41;">Failed At:</strong>
                        {{ $generatedAt }}
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 20px 16px;font-size:15px;color:#4A626A;text-align:left;">
                        <strong style="color:#B42318;">Error:</strong>
                        {{ $errorMessage }}
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 16px;font-size:13px;line-height:1.6;color:#8B9BA7;">
                This is an automated system email. The database backup was not successfully stored.
                Please investigate the issue before relying on the backup.
            </p>

            <p style="margin:0;font-size:14px;line-height:1.6;color:#546875;font-weight:600;">
                Team {{ $appName }}
            </p>

        </td>
    </tr>

@endsection