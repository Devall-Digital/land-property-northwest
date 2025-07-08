/* ===================================
   NORTHWEST PROPERTY & LAND
   Investment Calculator Module
   =================================== */

document.addEventListener('DOMContentLoaded', function() {
    initInvestmentCalculator();
});

function initInvestmentCalculator() {
    const propertyValue = document.getElementById('propertyValue');
    const deposit = document.getElementById('deposit');
    const mortgageRate = document.getElementById('mortgageRate');
    const rentalIncome = document.getElementById('rentalIncome');
    const investmentPeriod = document.getElementById('investmentPeriod');
    
    // Range value displays
    const depositValue = deposit?.nextElementSibling;
    const periodValue = investmentPeriod?.nextElementSibling;
    
    // Result elements
    const totalInvestment = document.getElementById('totalInvestment');
    const monthlyCashFlow = document.getElementById('monthlyCashFlow');
    const annualROI = document.getElementById('annualROI');
    const totalProfit = document.getElementById('totalProfit');
    
    // Chart
    let investmentChart = null;
    
    // Update range displays
    if (deposit) {
        deposit.addEventListener('input', function() {
            if (depositValue) {
                depositValue.textContent = this.value + '%';
            }
            calculateInvestment();
        });
    }
    
    if (investmentPeriod) {
        investmentPeriod.addEventListener('input', function() {
            if (periodValue) {
                periodValue.textContent = this.value + ' years';
            }
            calculateInvestment();
        });
    }
    
    // Add event listeners to all inputs
    [propertyValue, deposit, mortgageRate, rentalIncome, investmentPeriod].forEach(input => {
        if (input) {
            input.addEventListener('input', calculateInvestment);
        }
    });
    
    function calculateInvestment() {
        // Get values
        const price = parseFloat(propertyValue?.value) || 500000;
        const depositPercent = parseFloat(deposit?.value) || 25;
        const rate = parseFloat(mortgageRate?.value) || 4.5;
        const rental = parseFloat(rentalIncome?.value) || 2500;
        const years = parseFloat(investmentPeriod?.value) || 10;
        
        // Calculate investment metrics
        const depositAmount = price * (depositPercent / 100);
        const loanAmount = price - depositAmount;
        const monthlyRate = rate / 100 / 12;
        const numberOfPayments = 25 * 12; // 25-year mortgage
        
        // Monthly mortgage payment
        const monthlyPayment = loanAmount * 
            (monthlyRate * Math.pow(1 + monthlyRate, numberOfPayments)) / 
            (Math.pow(1 + monthlyRate, numberOfPayments) - 1);
        
        // Cash flow calculation
        const monthlyCash = rental - monthlyPayment;
        const annualCash = monthlyCash * 12;
        const roi = (annualCash / depositAmount) * 100;
        
        // Total profit over investment period
        const totalCash = annualCash * years;
        const propertyAppreciation = price * Math.pow(1.03, years) - price; // 3% annual appreciation
        const remainingMortgage = calculateRemainingMortgage(loanAmount, rate, years);
        const equity = price + propertyAppreciation - remainingMortgage;
        const profit = equity - depositAmount + totalCash;
        
        // Update UI
        updateResults({
            totalInvestment: depositAmount,
            monthlyCashFlow: monthlyCash,
            annualROI: roi,
            totalProfit: profit
        });
        
        // Update chart
        updateChart(years, depositAmount, price, propertyAppreciation, totalCash);
    }
    
    function calculateRemainingMortgage(principal, rate, yearsPassed) {
        const monthlyRate = rate / 100 / 12;
        const totalPayments = 25 * 12;
        const paymentsMade = yearsPassed * 12;
        const remainingPayments = totalPayments - paymentsMade;
        
        if (remainingPayments <= 0) return 0;
        
        // Calculate remaining balance
        const monthlyPayment = principal * 
            (monthlyRate * Math.pow(1 + monthlyRate, totalPayments)) / 
            (Math.pow(1 + monthlyRate, totalPayments) - 1);
            
        const remainingBalance = principal * 
            (Math.pow(1 + monthlyRate, totalPayments) - Math.pow(1 + monthlyRate, paymentsMade)) /
            (Math.pow(1 + monthlyRate, totalPayments) - 1);
            
        return remainingBalance;
    }
    
    function updateResults(results) {
        // Animate the number updates
        animateValue(totalInvestment, results.totalInvestment, '£');
        animateValue(monthlyCashFlow, results.monthlyCashFlow, '£');
        animateValue(annualROI, results.annualROI, '', '%');
        animateValue(totalProfit, results.totalProfit, '£');
        
        // Add positive/negative indicators
        if (monthlyCashFlow) {
            if (results.monthlyCashFlow >= 0) {
                monthlyCashFlow.style.color = 'var(--accent-gold)';
            } else {
                monthlyCashFlow.style.color = '#ff4444';
            }
        }
    }
    
    function animateValue(element, value, prefix = '', suffix = '') {
        if (!element) return;
        
        const startValue = parseFloat(element.textContent.replace(/[^0-9.-]/g, '')) || 0;
        const endValue = value;
        const duration = 500;
        const startTime = performance.now();
        
        function update() {
            const currentTime = performance.now();
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const currentValue = startValue + (endValue - startValue) * easeOutCubic(progress);
            
            if (prefix === '£') {
                element.textContent = prefix + formatNumber(Math.round(currentValue));
            } else {
                element.textContent = prefix + currentValue.toFixed(1) + suffix;
            }
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        update();
    }
    
    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }
    
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    function updateChart(years, deposit, propertyValue, appreciation, rentalProfit) {
        const ctx = document.getElementById('investmentChart');
        if (!ctx) return;
        
        // Generate data points
        const labels = [];
        const equityData = [];
        const rentalData = [];
        const totalData = [];
        
        for (let i = 0; i <= years; i++) {
            labels.push(`Year ${i}`);
            
            const yearAppreciation = propertyValue * (Math.pow(1.03, i) - 1);
            equityData.push(deposit + yearAppreciation);
            
            const yearRental = (rentalProfit / years) * i;
            rentalData.push(yearRental);
            
            totalData.push(deposit + yearAppreciation + yearRental);
        }
        
        // Chart configuration
        const chartConfig = {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Return',
                        data: totalData,
                        borderColor: '#ffcc00',
                        backgroundColor: 'rgba(255, 204, 0, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Property Equity',
                        data: equityData,
                        borderColor: '#ffffff',
                        backgroundColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Rental Income',
                        data: rentalData,
                        borderColor: '#666666',
                        backgroundColor: 'rgba(102, 102, 102, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#ffffff',
                            font: {
                                family: 'Inter',
                                size: 12
                            },
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#ffcc00',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': £' + formatNumber(Math.round(context.parsed.y));
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)',
                            borderColor: 'rgba(255, 255, 255, 0.2)'
                        },
                        ticks: {
                            color: '#999999',
                            font: {
                                family: 'Inter',
                                size: 11
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)',
                            borderColor: 'rgba(255, 255, 255, 0.2)'
                        },
                        ticks: {
                            color: '#999999',
                            font: {
                                family: 'Inter',
                                size: 11
                            },
                            callback: function(value) {
                                return '£' + formatNumber(value);
                            }
                        }
                    }
                }
            }
        };
        
        // Create or update chart
        if (investmentChart) {
            investmentChart.data = chartConfig.data;
            investmentChart.update();
        } else {
            investmentChart = new Chart(ctx, chartConfig);
        }
    }
    
    // Initial calculation
    calculateInvestment();
    
    // Add download report functionality
    const downloadBtn = document.createElement('button');
    downloadBtn.className = 'btn-futuristic btn-secondary';
    downloadBtn.innerHTML = '<span>DOWNLOAD INVESTMENT REPORT</span>';
    downloadBtn.style.marginTop = '2rem';
    
    const calculatorSection = document.querySelector('.investment-cta');
    if (calculatorSection) {
        calculatorSection.appendChild(downloadBtn);
    }
    
    downloadBtn.addEventListener('click', generateReport);
    
    function generateReport() {
        // Get current values
        const price = parseFloat(propertyValue?.value) || 500000;
        const depositPercent = parseFloat(deposit?.value) || 25;
        const rate = parseFloat(mortgageRate?.value) || 4.5;
        const rental = parseFloat(rentalIncome?.value) || 2500;
        const years = parseFloat(investmentPeriod?.value) || 10;
        
        // Create report content
        const reportContent = `
NORTHWEST PROPERTY & LAND INVESTMENT REPORT
Generated: ${new Date().toLocaleDateString()}

PROPERTY DETAILS
================
Property Value: £${formatNumber(price)}
Deposit: ${depositPercent}% (£${formatNumber(price * depositPercent / 100)})
Mortgage Rate: ${rate}%
Monthly Rental Income: £${formatNumber(rental)}
Investment Period: ${years} years

FINANCIAL SUMMARY
=================
Total Investment: £${formatNumber(price * depositPercent / 100)}
Monthly Cash Flow: £${formatNumber(rental - calculateMonthlyPayment(price, depositPercent, rate))}
Annual ROI: ${calculateROI(price, depositPercent, rate, rental).toFixed(1)}%
${years}-Year Total Return: £${formatNumber(calculateTotalReturn(price, depositPercent, rate, rental, years))}

PROJECTIONS
===========
Property value after ${years} years (3% annual growth): £${formatNumber(price * Math.pow(1.03, years))}
Total rental income: £${formatNumber(rental * 12 * years)}
Equity built: £${formatNumber(calculateEquity(price, depositPercent, rate, years))}

DISCLAIMER
==========
This report is for illustrative purposes only. Actual returns may vary.
Past performance is not indicative of future results.
Please consult with a financial advisor before making investment decisions.

Northwest Property & Land
Tel: +44 161 4XX XXXX
Email: invest@landpropertynorthwest.co.uk
Web: landpropertynorthwest.co.uk
        `;
        
        // Create download
        const blob = new Blob([reportContent], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Investment_Report_${new Date().toISOString().split('T')[0]}.txt`;
        link.click();
        URL.revokeObjectURL(url);
        
        // Show notification
        showNotification('Investment report downloaded successfully!');
    }
    
    function calculateMonthlyPayment(price, depositPercent, rate) {
        const loanAmount = price * (1 - depositPercent / 100);
        const monthlyRate = rate / 100 / 12;
        const numberOfPayments = 25 * 12;
        
        return loanAmount * 
            (monthlyRate * Math.pow(1 + monthlyRate, numberOfPayments)) / 
            (Math.pow(1 + monthlyRate, numberOfPayments) - 1);
    }
    
    function calculateROI(price, depositPercent, rate, rental) {
        const deposit = price * (depositPercent / 100);
        const monthlyPayment = calculateMonthlyPayment(price, depositPercent, rate);
        const annualCashFlow = (rental - monthlyPayment) * 12;
        return (annualCashFlow / deposit) * 100;
    }
    
    function calculateTotalReturn(price, depositPercent, rate, rental, years) {
        const deposit = price * (depositPercent / 100);
        const monthlyPayment = calculateMonthlyPayment(price, depositPercent, rate);
        const totalRentalIncome = rental * 12 * years;
        const totalPayments = monthlyPayment * 12 * years;
        const propertyAppreciation = price * (Math.pow(1.03, years) - 1);
        
        return totalRentalIncome - totalPayments + propertyAppreciation;
    }
    
    function calculateEquity(price, depositPercent, rate, years) {
        const deposit = price * (depositPercent / 100);
        const loanAmount = price - deposit;
        const remainingMortgage = calculateRemainingMortgage(loanAmount, rate, years);
        const futureValue = price * Math.pow(1.03, years);
        
        return futureValue - remainingMortgage;
    }
}

// Export for use in other modules
window.InvestmentCalculator = {
    init: initInvestmentCalculator
};