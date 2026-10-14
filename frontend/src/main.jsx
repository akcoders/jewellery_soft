import React from 'react';
import { createRoot } from 'react-dom/client';
import Dashboard from './Dashboard.jsx';
import './dashboard.css';

const mount = document.getElementById('erp-react-dashboard');
const payloadNode = document.getElementById('erp-dashboard-data');

if (mount && payloadNode) {
  let data = null;
  try {
    data = JSON.parse(payloadNode.textContent || '{}');
  } catch (error) {
    // Leave the server-rendered fallback in place.
    console.error('Dashboard payload could not be parsed', error);
  }
  if (data) {
    createRoot(mount).render(<Dashboard data={data} />);
  }
}
