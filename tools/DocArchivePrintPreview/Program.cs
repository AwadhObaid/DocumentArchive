using Microsoft.Win32;
using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Web;
using System.Windows.Forms;

namespace DocArchivePrintPreview
{
    internal static class Program
    {
        [STAThread]
        private static void Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            TrySetBrowserEmulation();

            LaunchRequest request;
            if (!LaunchRequest.TryCreate(args, out request))
            {
                MessageBox.Show(
                    "لم يتم تمرير رابط نموذج صحيح.\n\nالصيغة المتوقعة:\ndocarchive-print://preview?url=https%3A%2F%2Fexample.com%2Fform.htm",
                    "DocArchive Print Preview",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Warning,
                    MessageBoxDefaultButton.Button1,
                    MessageBoxOptions.RightAlign | MessageBoxOptions.RtlReading);
                return;
            }

            Application.Run(new PreviewForm(request));
        }

        private static void TrySetBrowserEmulation()
        {
            try
            {
                string exeName = Path.GetFileName(Application.ExecutablePath);
                using (RegistryKey key = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Internet Explorer\Main\FeatureControl\FEATURE_BROWSER_EMULATION"))
                {
                    if (key != null)
                    {
                        key.SetValue(exeName, 11001, RegistryValueKind.DWord); // IE11 Edge mode for the WinForms WebBrowser control.
                    }
                }
            }
            catch
            {
                // If registry writing fails, the app still works. It may only use an older IE document mode.
            }
        }
    }

    internal sealed class LaunchRequest
    {
        public Uri Url { get; private set; }
        public string Title { get; private set; }

        public static bool TryCreate(string[] args, out LaunchRequest request)
        {
            request = null;

            if (args == null || args.Length == 0 || string.IsNullOrWhiteSpace(args[0]))
            {
                return false;
            }

            string rawArgument = args[0].Trim();
            string targetUrl = rawArgument;
            string title = "نموذج";

            if (rawArgument.StartsWith("docarchive-print://", StringComparison.OrdinalIgnoreCase))
            {
                Uri protocolUri;
                if (!Uri.TryCreate(rawArgument, UriKind.Absolute, out protocolUri))
                {
                    return false;
                }

                var query = HttpUtility.ParseQueryString(protocolUri.Query);
                targetUrl = query.Get("url") ?? string.Empty;
                title = query.Get("title") ?? title;
            }

            Uri parsedUrl;
            if (!Uri.TryCreate(targetUrl, UriKind.Absolute, out parsedUrl))
            {
                return false;
            }

            string scheme = parsedUrl.Scheme.ToLowerInvariant();
            if (scheme != Uri.UriSchemeHttp && scheme != Uri.UriSchemeHttps)
            {
                return false;
            }

            request = new LaunchRequest
            {
                Url = parsedUrl,
                Title = string.IsNullOrWhiteSpace(title) ? "نموذج" : title.Trim()
            };

            return true;
        }
    }

    internal sealed class PreviewForm : Form
    {
        private readonly LaunchRequest request;
        private readonly WebBrowser browser;
        private Label statusLabel;
        private Label statusBadge;
        private Label titleLabel;
        private Label urlLabel;
        private Label hintLabel;
        private bool firstLoadDone;

        private static readonly Color PrimaryColor = Color.FromArgb(37, 99, 235);
        private static readonly Color PrimaryDark = Color.FromArgb(29, 78, 216);
        private static readonly Color Slate900 = Color.FromArgb(15, 23, 42);
        private static readonly Color Slate700 = Color.FromArgb(51, 65, 85);
        private static readonly Color Slate500 = Color.FromArgb(100, 116, 139);
        private static readonly Color BorderColor = Color.FromArgb(203, 213, 225);
        private static readonly Color SurfaceColor = Color.FromArgb(248, 250, 252);
        private static readonly Color SoftBlue = Color.FromArgb(239, 246, 255);
        private static readonly Color SuccessBg = Color.FromArgb(220, 252, 231);
        private static readonly Color SuccessText = Color.FromArgb(22, 101, 52);
        private static readonly Color WarningBg = Color.FromArgb(254, 249, 195);
        private static readonly Color WarningText = Color.FromArgb(133, 77, 14);

        public PreviewForm(LaunchRequest request)
        {
            this.request = request;

            Text = "DocArchive - " + request.Title;
            Width = 1280;
            Height = 860;
            MinimumSize = new Size(1000, 650);
            StartPosition = FormStartPosition.CenterScreen;
            Font = new Font("Segoe UI", 9F, FontStyle.Regular, GraphicsUnit.Point);
            RightToLeft = RightToLeft.Yes;
            RightToLeftLayout = false;
            WindowState = FormWindowState.Maximized;
            BackColor = Color.White;
            KeyPreview = true;

            Panel rootPanel = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = Color.White,
                Padding = new Padding(0)
            };

            Panel headerPanel = BuildHeaderPanel();
            Panel actionPanel = BuildActionPanel();
            Panel footerPanel = BuildFooterPanel();

            browser = new WebBrowser
            {
                Dock = DockStyle.Fill,
                ScriptErrorsSuppressed = true,
                AllowNavigation = true,
                AllowWebBrowserDrop = false,
                IsWebBrowserContextMenuEnabled = true,
                WebBrowserShortcutsEnabled = true
            };

            Panel browserFrame = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = Color.White,
                Padding = new Padding(8, 0, 8, 8)
            };
            browserFrame.Controls.Add(browser);

            browser.Navigating += delegate { SetLoadingState("جاري تحميل النموذج..."); };
            browser.DocumentCompleted += Browser_DocumentCompleted;

            rootPanel.Controls.Add(browserFrame);
            rootPanel.Controls.Add(footerPanel);
            rootPanel.Controls.Add(actionPanel);
            rootPanel.Controls.Add(headerPanel);
            Controls.Add(rootPanel);

            Load += delegate { browser.Navigate(request.Url); };
            KeyDown += PreviewForm_KeyDown;
        }

        private Panel BuildHeaderPanel()
        {
            Panel headerPanel = new Panel
            {
                Dock = DockStyle.Top,
                Height = 112,
                BackColor = SurfaceColor,
                Padding = new Padding(18, 12, 18, 10)
            };

            Label appLabel = new Label
            {
                Name = "appLabel",
                Text = "DocArchive",
                AutoSize = false,
                TextAlign = ContentAlignment.MiddleRight,
                Font = new Font("Segoe UI", 18F, FontStyle.Bold, GraphicsUnit.Point),
                ForeColor = PrimaryColor,
                RightToLeft = RightToLeft.No
            };

            Label appSubLabel = new Label
            {
                Name = "appSubLabel",
                Text = "معاينة الطباعة القديمة",
                AutoSize = false,
                TextAlign = ContentAlignment.MiddleRight,
                Font = new Font("Segoe UI", 9F, FontStyle.Regular, GraphicsUnit.Point),
                ForeColor = Slate500,
                RightToLeft = RightToLeft.Yes
            };

            titleLabel = new Label
            {
                Text = request.Title,
                AutoSize = false,
                AutoEllipsis = true,
                TextAlign = ContentAlignment.MiddleRight,
                Font = new Font("Segoe UI", 16F, FontStyle.Bold, GraphicsUnit.Point),
                ForeColor = Slate900,
                RightToLeft = RightToLeft.Yes
            };

            hintLabel = new Label
            {
                Text = "عبّئ النموذج داخل هذه النافذة، ثم اضغط معاينة الطباعة القديمة.",
                AutoSize = false,
                AutoEllipsis = true,
                TextAlign = ContentAlignment.MiddleRight,
                Font = new Font("Segoe UI", 9.5F, FontStyle.Regular, GraphicsUnit.Point),
                ForeColor = Slate700,
                RightToLeft = RightToLeft.Yes
            };

            statusBadge = new Label
            {
                Text = "جاري التحميل",
                AutoSize = false,
                TextAlign = ContentAlignment.MiddleCenter,
                Font = new Font("Segoe UI", 9F, FontStyle.Bold, GraphicsUnit.Point),
                ForeColor = WarningText,
                BackColor = WarningBg,
                BorderStyle = BorderStyle.FixedSingle,
                RightToLeft = RightToLeft.Yes
            };

            urlLabel = new Label
            {
                Text = request.Url.ToString(),
                AutoSize = false,
                AutoEllipsis = true,
                TextAlign = ContentAlignment.MiddleLeft,
                Font = new Font("Segoe UI", 8.5F, FontStyle.Regular, GraphicsUnit.Point),
                ForeColor = Slate500,
                RightToLeft = RightToLeft.No
            };

            headerPanel.Controls.Add(appLabel);
            headerPanel.Controls.Add(appSubLabel);
            headerPanel.Controls.Add(titleLabel);
            headerPanel.Controls.Add(hintLabel);
            headerPanel.Controls.Add(statusBadge);
            headerPanel.Controls.Add(urlLabel);

            headerPanel.Resize += delegate { LayoutHeaderPanel(headerPanel); };
            headerPanel.HandleCreated += delegate { LayoutHeaderPanel(headerPanel); };

            return headerPanel;
        }

        private void LayoutHeaderPanel(Panel headerPanel)
        {
            int padding = 18;
            int top = 12;
            int brandWidth = 245;
            int statusWidth = 270;
            int gap = 18;

            Control appLabel = headerPanel.Controls["appLabel"];
            Control appSubLabel = headerPanel.Controls["appSubLabel"];

            int rightX = Math.Max(padding, headerPanel.ClientSize.Width - padding - brandWidth);
            appLabel.SetBounds(rightX, top + 2, brandWidth, 36);
            appSubLabel.SetBounds(rightX, top + 42, brandWidth, 24);

            statusBadge.SetBounds(padding, top + 4, 190, 28);
            urlLabel.SetBounds(padding, top + 38, statusWidth, 42);

            int centerLeft = padding + statusWidth + gap;
            int centerRight = rightX - gap;
            int centerWidth = Math.Max(260, centerRight - centerLeft);

            titleLabel.SetBounds(centerLeft, top + 0, centerWidth, 38);
            hintLabel.SetBounds(centerLeft, top + 43, centerWidth, 30);
        }

        private Panel BuildActionPanel()
        {
            Panel actionPanel = new Panel
            {
                Dock = DockStyle.Top,
                Height = 84,
                BackColor = Color.White,
                Padding = new Padding(18, 12, 18, 10)
            };

            Button previewButton = CreateActionButton("🖨  معاينة الطباعة القديمة", 200, true);
            previewButton.Click += delegate { ShowLegacyPrintPreview(); };

            Button printButton = CreateActionButton("طباعة مباشرة", 130, false);
            printButton.Click += delegate { ShowPrintDialog(); };

            Button pageSetupButton = CreateActionButton("إعداد الصفحة", 130, false);
            pageSetupButton.Tag = "group-end";
            pageSetupButton.Click += delegate { ShowPageSetup(); };

            Button refreshButton = CreateActionButton("تحديث النموذج", 130, false);
            refreshButton.Click += delegate { browser.Refresh(WebBrowserRefreshOption.Completely); };

            Button copyButton = CreateActionButton("نسخ الرابط", 115, false);
            copyButton.Click += delegate { CopyLink(); };

            Button openBrowserButton = CreateActionButton("فتح في المتصفح", 135, false);
            openBrowserButton.Click += delegate { OpenInDefaultBrowser(); };

            Button closeButton = CreateActionButton("إغلاق", 95, false);
            closeButton.Click += delegate { Close(); };

            actionPanel.Controls.Add(previewButton);
            actionPanel.Controls.Add(printButton);
            actionPanel.Controls.Add(pageSetupButton);
            actionPanel.Controls.Add(refreshButton);
            actionPanel.Controls.Add(copyButton);
            actionPanel.Controls.Add(openBrowserButton);
            actionPanel.Controls.Add(closeButton);

            actionPanel.Resize += delegate { LayoutActionButtons(actionPanel); };
            actionPanel.HandleCreated += delegate { LayoutActionButtons(actionPanel); };

            return actionPanel;
        }

        private void LayoutActionButtons(Panel actionPanel)
        {
            int x = actionPanel.ClientSize.Width - actionPanel.Padding.Right;
            int y = actionPanel.Padding.Top;
            int buttonHeight = 46;
            int gap = 8;
            int groupGap = 18;

            foreach (Control control in actionPanel.Controls)
            {
                Button button = control as Button;
                if (button == null)
                {
                    continue;
                }

                x -= button.Width;
                button.SetBounds(Math.Max(actionPanel.Padding.Left, x), y, button.Width, buttonHeight);
                x -= gap;

                if ((button.Tag as string) == "group-end")
                {
                    x -= groupGap;
                }
            }
        }

        private Panel BuildFooterPanel()
        {
            Panel footerPanel = new Panel
            {
                Dock = DockStyle.Bottom,
                Height = 36,
                BackColor = SoftBlue,
                Padding = new Padding(14, 5, 14, 5)
            };

            statusLabel = new Label
            {
                Dock = DockStyle.Fill,
                AutoEllipsis = true,
                TextAlign = ContentAlignment.MiddleRight,
                Font = new Font("Segoe UI", 9F, FontStyle.Regular, GraphicsUnit.Point),
                ForeColor = Slate700,
                Text = "جاهز",
                RightToLeft = RightToLeft.Yes
            };

            footerPanel.Controls.Add(statusLabel);
            return footerPanel;
        }

        private Button CreateActionButton(string text, int width, bool primary)
        {
            Button button = new Button
            {
                Text = text,
                Width = width,
                Height = 44,
                Margin = new Padding(5, 0, 5, 0),
                FlatStyle = FlatStyle.Flat,
                Cursor = Cursors.Hand,
                UseVisualStyleBackColor = false,
                Font = new Font("Segoe UI", 9F, primary ? FontStyle.Bold : FontStyle.Regular, GraphicsUnit.Point),
                TextAlign = ContentAlignment.MiddleCenter,
                AutoEllipsis = false,
                RightToLeft = RightToLeft.Yes,
                Padding = new Padding(8, 0, 8, 0)
            };

            if (primary)
            {
                button.BackColor = PrimaryColor;
                button.ForeColor = Color.White;
                button.FlatAppearance.BorderColor = PrimaryDark;
            }
            else
            {
                button.BackColor = SurfaceColor;
                button.ForeColor = Slate900;
                button.FlatAppearance.BorderColor = BorderColor;
            }

            button.FlatAppearance.BorderSize = 1;
            return button;
        }

        private void Browser_DocumentCompleted(object sender, WebBrowserDocumentCompletedEventArgs e)
        {
            if (e.Url == null || browser.Url == null || e.Url.AbsoluteUri != browser.Url.AbsoluteUri)
            {
                return;
            }

            statusLabel.Text = "تم التحميل. عبّئ النموذج داخل هذه النافذة، ثم اضغط معاينة الطباعة القديمة.";
            statusBadge.Text = "تم التحميل";
            statusBadge.ForeColor = SuccessText;
            statusBadge.BackColor = SuccessBg;
            Text = "DocArchive - " + request.Title;

            if (!firstLoadDone)
            {
                firstLoadDone = true;
                Activate();
            }
        }

        private void PreviewForm_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Control && e.KeyCode == Keys.P)
            {
                e.Handled = true;
                ShowPrintDialog();
            }
            else if (e.Control && e.Shift && e.KeyCode == Keys.P)
            {
                e.Handled = true;
                ShowLegacyPrintPreview();
            }
            else if (e.KeyCode == Keys.F5)
            {
                e.Handled = true;
                browser.Refresh(WebBrowserRefreshOption.Completely);
            }
        }

        private void SetLoadingState(string message)
        {
            statusLabel.Text = message;
            statusBadge.Text = "جاري التحميل";
            statusBadge.ForeColor = WarningText;
            statusBadge.BackColor = WarningBg;
        }

        private void ShowLegacyPrintPreview()
        {
            try
            {
                if (browser.ReadyState != WebBrowserReadyState.Complete)
                {
                    MessageBox.Show(
                        "انتظر حتى يكتمل تحميل النموذج أولاً.",
                        "DocArchive Print Preview",
                        MessageBoxButtons.OK,
                        MessageBoxIcon.Information,
                        MessageBoxDefaultButton.Button1,
                        MessageBoxOptions.RightAlign | MessageBoxOptions.RtlReading);
                    return;
                }

                browser.ShowPrintPreviewDialog();
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    "تعذر فتح معاينة الطباعة القديمة.\n\n" + ex.Message,
                    "DocArchive Print Preview",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error,
                    MessageBoxDefaultButton.Button1,
                    MessageBoxOptions.RightAlign | MessageBoxOptions.RtlReading);
            }
        }

        private void ShowPrintDialog()
        {
            try
            {
                browser.ShowPrintDialog();
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    "تعذرت الطباعة.\n\n" + ex.Message,
                    "DocArchive Print Preview",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error,
                    MessageBoxDefaultButton.Button1,
                    MessageBoxOptions.RightAlign | MessageBoxOptions.RtlReading);
            }
        }

        private void ShowPageSetup()
        {
            try
            {
                browser.ShowPageSetupDialog();
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    "تعذر فتح إعداد الصفحة.\n\n" + ex.Message,
                    "DocArchive Print Preview",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error,
                    MessageBoxDefaultButton.Button1,
                    MessageBoxOptions.RightAlign | MessageBoxOptions.RtlReading);
            }
        }

        private void CopyLink()
        {
            try
            {
                Clipboard.SetText(request.Url.ToString());
                statusLabel.Text = "تم نسخ رابط النموذج.";
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    "تعذر نسخ الرابط.\n\n" + ex.Message,
                    "DocArchive Print Preview",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error,
                    MessageBoxDefaultButton.Button1,
                    MessageBoxOptions.RightAlign | MessageBoxOptions.RtlReading);
            }
        }

        private void OpenInDefaultBrowser()
        {
            try
            {
                Process.Start(new ProcessStartInfo
                {
                    FileName = request.Url.ToString(),
                    UseShellExecute = true
                });
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    "تعذر فتح الرابط في المتصفح.\n\n" + ex.Message,
                    "DocArchive Print Preview",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error,
                    MessageBoxDefaultButton.Button1,
                    MessageBoxOptions.RightAlign | MessageBoxOptions.RtlReading);
            }
        }
    }
}
