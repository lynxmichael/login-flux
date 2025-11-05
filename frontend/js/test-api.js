const axios = require('axios');

const API_BASE = 'http://localhost:3000/api';

async function testAPI() {
  try {
    console.log('Testing API connection...');
    
    // Test assets endpoint
    const assetsResponse = await axios.get(`${API_BASE}/assets`);
    console.log('✅ Assets endpoint:', assetsResponse.data.length, 'assets found');
    
    // Test market data endpoint
    const marketResponse = await axios.get(`${API_BASE}/market-data`);
    console.log('✅ Market data endpoint:', marketResponse.data);
    
    // Test search endpoint
    const searchResponse = await axios.get(`${API_BASE}/assets/search/boa`);
    console.log('✅ Search endpoint:', searchResponse.data.length, 'results');
    
    console.log('🎉 All API endpoints working correctly!');
    
  } catch (error) {
    console.error('❌ API test failed:', error.message);
  }
}

testAPI();