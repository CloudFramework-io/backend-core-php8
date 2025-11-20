# Email Class

## Overview

The `Email` class provides email sending functionality with support for SendGrid integration. It offers a fluent API for building and sending emails with attachments, templates, and multiple recipients.

## Loading the Class

```php
$email = $this->core->loadClass('Email');
```

## Main Methods

### setTo()

```php
public function setTo($email): self
```

Sets the primary recipient email address.

**Example:**
```php
$email = $this->core->loadClass('Email');
$email->setTo('user@example.com');
```

---

### addTo()

```php
public function addTo($email, $name = null): self
```

Adds a recipient (supports multiple recipients).

**Example:**
```php
$email->addTo('user@example.com', 'John Doe');
$email->addTo('another@example.com');
```

---

### setFrom()

```php
public function setFrom($email): self
```

Sets the sender email address.

**Example:**
```php
$email->setFrom('noreply@myapp.com');
```

---

### setFromName()

```php
public function setFromName($name): self
```

Sets the sender name.

**Example:**
```php
$email->setFromName('My Application');
```

---

### setReplyTo()

```php
public function setReplyTo($email): self
```

Sets the reply-to email address.

---

### setSubject()

```php
public function setSubject($subject): self
```

Sets the email subject.

**Example:**
```php
$email->setSubject('Welcome to My App');
```

---

### setHtml()

```php
public function setHtml($html): self
```

Sets the HTML body of the email.

**Example:**
```php
$html = '<h1>Welcome!</h1><p>Thanks for joining our platform.</p>';
$email->setHtml($html);
```

---

### setText()

```php
public function setText($text): self
```

Sets the plain text body.

**Example:**
```php
$email->setText('Welcome! Thanks for joining our platform.');
```

---

### addCc()

```php
public function addCc($email): self
```

Adds a CC recipient.

---

### addBcc()

```php
public function addBcc($email): self
```

Adds a BCC recipient.

---

### addAttachment()

```php
public function addAttachment($path, $name = null): self
```

Attaches a file to the email.

**Parameters:**
- `$path` (string): File path
- `$name` (string|null): Optional file name

**Example:**
```php
$email->addAttachment('/path/to/document.pdf', 'invoice.pdf');
```

---

### send()

```php
public function send(): bool
```

Sends the email.

**Returns:** `true` on success, `false` on error

**Example:**
```php
if($email->send()) {
    // Email sent successfully
} else {
    // Handle error
}
```

---

## Common Usage Patterns

### Simple Email

```php
$email = $this->core->loadClass('Email');
$email->setFrom('noreply@myapp.com')
      ->setFromName('My Application')
      ->setTo('user@example.com')
      ->setSubject('Welcome!')
      ->setHtml('<h1>Welcome!</h1><p>Thanks for joining.</p>')
      ->send();
```

### Email with Multiple Recipients

```php
$email = $this->core->loadClass('Email');
$email->setFrom('noreply@myapp.com')
      ->setSubject('Team Announcement')
      ->addTo('user1@example.com', 'User One')
      ->addTo('user2@example.com', 'User Two')
      ->addCc('manager@example.com')
      ->setHtml('<p>Important team update...</p>')
      ->send();
```

### Email with Attachment

```php
$email = $this->core->loadClass('Email');
$email->setFrom('billing@myapp.com')
      ->setTo($user['email'])
      ->setSubject('Your Invoice')
      ->setHtml('<p>Please find your invoice attached.</p>')
      ->addAttachment('/tmp/invoice-' . $invoiceId . '.pdf', 'invoice.pdf')
      ->send();
```

### Template-Based Email

```php
$email = $this->core->loadClass('Email');

// Load template
$template = file_get_contents($this->core->system->app_path . '/templates/welcome-email.html');

// Replace placeholders
$html = str_replace([
    '{{name}}',
    '{{email}}',
    '{{activation_link}}'
], [
    $user['name'],
    $user['email'],
    'https://myapp.com/activate?token=' . $token
], $template);

$email->setFrom('noreply@myapp.com')
      ->setTo($user['email'])
      ->setSubject('Activate Your Account')
      ->setHtml($html)
      ->send();
```

### Transactional Emails

```php
// Order confirmation
public function sendOrderConfirmation($orderId, $userEmail)
{
    $order = $this->getOrder($orderId);

    $email = $this->core->loadClass('Email');
    $email->setFrom('orders@myapp.com')
          ->setFromName('My Shop')
          ->setTo($userEmail)
          ->setSubject('Order Confirmation #' . $orderId)
          ->setHtml($this->buildOrderEmailHtml($order))
          ->send();
}

// Password reset
public function sendPasswordReset($userEmail, $resetToken)
{
    $resetLink = 'https://myapp.com/reset-password?token=' . $resetToken;

    $email = $this->core->loadClass('Email');
    $email->setFrom('noreply@myapp.com')
          ->setTo($userEmail)
          ->setSubject('Reset Your Password')
          ->setHtml("
              <h2>Reset Your Password</h2>
              <p>Click the link below to reset your password:</p>
              <p><a href='{$resetLink}'>Reset Password</a></p>
              <p>This link expires in 1 hour.</p>
          ")
          ->send();
}
```

---

## Configuration

Configure email settings in `config.json`:

```json
{
  "email.from": "noreply@myapp.com",
  "email.from_name": "My Application",
  "sendgrid.api_key": "SG.your-api-key-here"
}
```

---

## See Also

- [Core7 Class Reference](Core7.md)
- [API Examples](../examples/api-examples.md)
