<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 Internal Server Error</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@800&family=Roboto:wght@100;300&display=swap');

    body {
      min-height: 100vh;
      display: flex;
      font-family: 'Roboto', sans-serif;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background-color: #ff8c00; /* Dark orange background color */
      color: #ffffff; /* White text color */
      margin: 0; /* Reset default margin */
      padding: 0; /* Reset default padding */
    }

    a, button {
      text-transform: uppercase;
      text-decoration: none;
      background-color: #ffd600; /* Dark yellow background color */
      color: #ffffff; /* White text color */
      padding: 1rem 4rem;
      border-radius: 4rem;
      font-size: 0.875rem;
      letter-spacing: 0.05rem;
      margin-top: 2rem;
      margin: 0 10px;
      display: inline-block;
      border: none;
      cursor: pointer;
      font-family: 'Roboto', sans-serif;
    }

    a:hover, button:hover {
      background-color: #ffb300;
      color: #ffffff;
      text-decoration: none;
    }

    h1 {
      font-size: clamp(5rem, 40vmin, 20rem);
      font-family: 'Open Sans', sans-serif;
      margin: 0;
      margin-bottom: 1rem;
      letter-spacing: 1rem;
    }

    .info {
      text-align: center;
      line-height: 1.5;
      max-width: clamp(16rem, 90vmin, 25rem);
    }

    .info > p {
      margin-bottom: 3rem;
    }
  </style>
</head>
<body>
  <h1>500</h1>
  <div class="info">
    <h2>Internal Server Error</h2>
    <p>
      We're experiencing some technical difficulties right now. Our team is working hard to fix it. Please try again later.
    </p>
    <button onclick="window.location.reload()">Try Again</button>
    <a href="/">Home</a>
    <a href="javascript:history.back()">Go Back</a>
  </div>
</body>
</html>
