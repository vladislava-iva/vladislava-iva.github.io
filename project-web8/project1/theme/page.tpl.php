<style>
body {background-color: white; color: black; font-family: "Bitstream Vera Sans", Tahoma, Verdana, Arial, sans-serif; font-size: 76%;}
h1 {text-align:center;}
h2, .hr {border-top: 1px dotted gray;}
table {border: 1px dotted gray;}
th {background-color: #ccc; font-size: 76%;}
td {height: 2em; padding: 1px 2px 1px 2px; font-size: 76%;}
input, select {font-size: 76%;}
.a td {background-color: #e0e0e0;}
.a, .b {height: 1.2em;}
.a td form, .b td form {margin:0;}
a, a:visited {color: #339; text-decoration: underline; font-weight: 700;}
form {margin-top: 15px;}
input {font-size: 100%;}
ul {margin-bottom: 1em;}
li {margin-bottom: 0.3em;}
</style>

<?php
foreach ($c['#content'] as $content) {
  echo $content;
}
?>